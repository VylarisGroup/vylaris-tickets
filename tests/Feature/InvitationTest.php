<?php

namespace Tests\Feature;

use App\Enums\OrganizerRole;
use App\Enums\ResellerRole;
use App\Mail\InvitationMail;
use App\Mail\OrderPaidMail;
use App\Models\Invitation;
use App\Models\Organizer;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private Reseller $reseller;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reseller = Reseller::create(['name' => 'Eventhaus', 'slug' => 'eventhaus', 'brand_name' => 'Eventhaus Bern', 'brand_color' => '#e11d48', 'support_email' => 'hilfe@eventhaus.ch', 'max_organizers' => 5, 'max_events_per_organizer' => 10]);
        $this->manager = User::factory()->create();
        $this->reseller->users()->attach($this->manager, ['role' => ResellerRole::Owner->value]);
    }

    private function create(array $extra = [])
    {
        return $this->actingAs($this->manager)->post(route('reseller.organizers.store', $this->reseller), $extra + ['name' => 'Verein A', 'owner_name' => 'Ina', 'owner_email' => 'ina@example.ch']);
    }

    public function test_new_owner_gets_invitation_mail_and_sets_password(): void
    {
        Mail::fake();
        $this->create()->assertRedirect();

        $organizer = Organizer::firstOrFail();
        $this->assertSame(0, $organizer->users()->count());
        $this->assertDatabaseCount('users', 1);

        $token = null;
        Mail::assertSent(InvitationMail::class, function (InvitationMail $m) use (&$token) {
            $token = $m->token;

            return $m->hasTo('ina@example.ch') && $m->hasReplyTo('hilfe@eventhaus.ch');
        });

        auth()->logout();
        $this->get(route('invitation.show', $token))->assertOk()->assertSee('Verein A')->assertSee('ina@example.ch');

        $this->post(route('invitation.accept', $token), ['name' => 'Ina Muster', 'password' => 'ein-langes-passwort', 'password_confirmation' => 'ein-langes-passwort'])
            ->assertRedirect(route('organizer.dashboard', $organizer));

        $user = User::where('email', 'ina@example.ch')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(OrganizerRole::Owner->value, $organizer->users()->first()->pivot->role);
        $this->assertAuthenticatedAs($user);

        // Der Link funktioniert nur einmal.
        $this->post(route('invitation.accept', $token), ['name' => 'X', 'password' => 'ein-langes-passwort', 'password_confirmation' => 'ein-langes-passwort'])->assertNotFound();
    }

    public function test_existing_account_is_attached_without_invitation(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'ina@example.ch']);
        $this->create()->assertRedirect();

        Mail::assertNothingSent();
        $this->assertSame($user->id, Organizer::firstOrFail()->users()->first()->id);
    }

    public function test_expired_or_unknown_token_is_rejected(): void
    {
        Mail::fake();
        $this->create();
        $inv = Invitation::firstOrFail();
        $inv->update(['expires_at' => now()->subMinute()]);

        $this->get(route('invitation.show', 'falsch'))->assertNotFound();
        Mail::assertSent(InvitationMail::class, fn ($m) => $this->get(route('invitation.show', $m->token))->assertNotFound() !== null);
    }

    public function test_resend_replaces_old_link_and_revoke_removes_it(): void
    {
        Mail::fake();
        $this->create();
        $inv = Invitation::firstOrFail();
        $first = null;
        Mail::assertSent(InvitationMail::class, function ($m) use (&$first) {
            $first = $m->token;

            return true;
        });

        $this->actingAs($this->manager)->post(route('reseller.invitations.resend', [$this->reseller, $inv]))->assertRedirect();
        $this->assertSame(1, Invitation::count());
        $this->get(route('invitation.show', $first))->assertNotFound();

        $this->actingAs($this->manager)->delete(route('reseller.invitations.destroy', [$this->reseller, Invitation::first()]))->assertRedirect();
        $this->assertSame(0, Invitation::count());
    }

    public function test_other_reseller_cannot_manage_invitation(): void
    {
        Mail::fake();
        $this->create();
        $other = Reseller::create(['name' => 'Anders', 'slug' => 'anders']);
        $this->reseller->users()->detach($this->manager);
        $other->users()->attach($this->manager, ['role' => ResellerRole::Owner->value]);

        $this->actingAs($this->manager)->post(route('reseller.invitations.resend', [$other, Invitation::first()]))->assertNotFound();
    }

    public function test_pending_invitation_and_limits_are_shown(): void
    {
        Mail::fake();
        $this->create();

        $this->actingAs($this->manager)->get(route('reseller.organizers.index', $this->reseller))
            ->assertOk()->assertSee('Einladung an ina@example.ch')->assertSee('Erneut senden')->assertSee('/ 10', false);

        $this->actingAs($this->manager)->get(route('reseller.dashboard', $this->reseller))
            ->assertOk()->assertSee('Limits')->assertSee('1 offene Einladung');
    }

    public function test_dashboard_warns_when_organizer_is_near_event_limit(): void
    {
        $o = Organizer::create(['reseller_id' => $this->reseller->id, 'name' => 'Voll', 'slug' => 'voll']);
        for ($i = 0; $i < 9; $i++) {
            $o->events()->create(['title' => "E$i", 'slug' => "e$i", 'starts_at' => now()->addDay(), 'status' => 'draft', 'type' => 'onsite', 'currency' => 'CHF']);
        }

        $this->actingAs($this->manager)->get(route('reseller.dashboard', $this->reseller))
            ->assertOk()->assertSee('Event-Limit fast erreicht')->assertSee('Voll: 9 / 10');
    }

    public function test_mails_carry_reseller_branding(): void
    {
        $html = view('mail.invitation', [
            'invitation' => new Invitation(['name' => 'Ina', 'email' => 'i@e.ch']),
            'organizer' => Organizer::create(['reseller_id' => $this->reseller->id, 'name' => 'V', 'slug' => 'v']),
            'reseller' => $this->reseller,
            'url' => 'https://example.test/x',
        ])->render();

        $this->assertStringContainsString('#e11d48', $html);
        $this->assertStringContainsString('Eventhaus Bern', $html);
        $this->assertStringContainsString('hilfe@eventhaus.ch', $html);

        $plain = view('mail.invitation', [
            'invitation' => new Invitation(['name' => 'Ina', 'email' => 'i@e.ch']),
            'organizer' => Organizer::create(['name' => 'W', 'slug' => 'w']),
            'reseller' => null,
            'url' => 'https://example.test/x',
        ])->render();
        $this->assertStringNotContainsString('hilfe@eventhaus.ch', $plain);
    }
}
