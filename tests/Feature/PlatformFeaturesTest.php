<?php

namespace Tests\Feature;

use App\Mail\EventMessageMail;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private Organizer $organizer;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a']);
        $this->owner = User::factory()->create();
        $this->organizer->users()->attach($this->owner, ['role' => 'owner']);
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_platform_admin' => true])->save();
    }

    private function event(array $over = []): Event
    {
        return $this->organizer->events()->create(array_merge([
            'title' => 'Fest', 'slug' => 'fest', 'type' => 'onsite', 'venue_name' => 'Halle',
            'starts_at' => now()->addMonth(), 'currency' => 'CHF', 'status' => 'published',
        ], $over));
    }

    private function wizardPayload(array $over = []): array
    {
        return array_merge([
            'title' => 'Open Air', 'type' => 'onsite', 'venue_name' => 'Wiese', 'starts_at' => '2027-07-01T20:00',
            'currency' => 'CHF', 'category' => 'konzert', 'city' => 'Bern',
            'tickets' => [
                ['name' => 'Standard', 'price' => '25,50', 'quota' => 100, 'max_per_order' => 10],
                ['name' => 'Gratis', 'price' => '0', 'max_per_order' => 2],
            ],
        ], $over);
    }

    public function test_wizard_creates_event_with_tickets_as_draft(): void
    {
        $this->actingAs($this->owner)->get(route('organizer.wizard', $this->organizer))->assertOk();
        $this->actingAs($this->owner)->post(route('organizer.wizard.store', $this->organizer), $this->wizardPayload())->assertRedirect();

        $event = $this->organizer->events()->firstOrFail();
        $this->assertSame('draft', $event->status->value);
        $this->assertSame(2, $event->ticketTypes()->count());
        $this->assertSame(2550, $event->ticketTypes()->orderBy('sort')->first()->price_cents);
    }

    public function test_wizard_can_publish_and_validates(): void
    {
        $this->actingAs($this->owner)->post(route('organizer.wizard.store', $this->organizer), $this->wizardPayload(['publish' => '1']));
        $this->assertSame('published', $this->organizer->events()->first()->status->value);

        $this->actingAs($this->owner)->post(route('organizer.wizard.store', $this->organizer), $this->wizardPayload(['tickets' => []]))
            ->assertSessionHasErrors('tickets');
        $this->assertSame(1, $this->organizer->events()->count());
    }

    public function test_cover_upload_and_duplicate(): void
    {
        Storage::fake('public');
        $this->actingAs($this->owner)->post(route('organizer.wizard.store', $this->organizer), $this->wizardPayload([
            'cover' => UploadedFile::fake()->image('c.jpg', 800, 400),
        ]));
        $event = $this->organizer->events()->firstOrFail();
        $this->assertNotNull($event->cover_path);
        Storage::disk('public')->assertExists($event->cover_path);

        $this->actingAs($this->owner)->post(route('organizer.events.duplicate', [$this->organizer, $event]))->assertRedirect();
        $this->assertSame(2, $this->organizer->events()->count());
        $copy = $this->organizer->events()->where('id', '!=', $event->id)->first();
        $this->assertSame('draft', $copy->status->value);
        $this->assertSame(2, $copy->ticketTypes()->count());
    }

    public function test_pages_render_for_owner(): void
    {
        $event = $this->event();
        foreach ([
            route('organizer.dashboard', $this->organizer),
            route('organizer.events.index', $this->organizer),
            route('organizer.events.show', [$this->organizer, $event]),
            route('organizer.stats', $this->organizer),
            route('organizer.orders.all', $this->organizer),
            route('organizer.attendees.index', [$this->organizer, $event]),
            route('organizer.messages.create', [$this->organizer, $event]),
            route('account.edit'),
        ] as $url) {
            $this->actingAs($this->owner)->get($url)->assertOk();
        }
    }

    public function test_message_is_sent_to_buyers(): void
    {
        Mail::fake();
        $event = $this->event();

        $this->actingAs($this->owner)->post(route('organizer.messages.store', [$this->organizer, $event]), [
            'subject' => 'Hallo', 'body' => 'Infos', 'test' => '1',
        ])->assertRedirect();

        Mail::assertSent(EventMessageMail::class);
    }

    public function test_account_update_and_password(): void
    {
        $this->actingAs($this->owner)->put(route('account.update'), ['name' => 'Neu', 'email' => 'neu@example.com'])->assertSessionHasNoErrors();
        $this->assertSame('Neu', $this->owner->fresh()->name);

        $this->actingAs($this->owner)->put(route('account.password'), [
            'current_password' => 'password', 'password' => 'NeuesPasswort123!', 'password_confirmation' => 'NeuesPasswort123!',
        ])->assertSessionHasNoErrors();
    }

    public function test_admin_user_crud_and_guards(): void
    {
        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertSessionHasErrors();
        $this->assertNotNull($this->admin->fresh());

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->owner))->assertRedirect();
        $this->assertNull(User::find($this->owner->id));

        $this->actingAs($this->owner)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_organizer_edit_and_delete_guard(): void
    {
        $this->actingAs($this->admin)->get(route('admin.organizers.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.organizers.edit', $this->organizer))->assertOk();
        $this->actingAs($this->admin)->put(route('admin.organizers.update', $this->organizer), [
            'name' => 'Umbenannt', 'default_currency' => 'EUR', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame('Umbenannt', $this->organizer->fresh()->name);

        $this->actingAs($this->admin)->delete(route('admin.organizers.destroy', $this->organizer))->assertRedirect();
        $this->assertNull(Organizer::find($this->organizer->id));
    }

    public function test_reseller_organizer_edit(): void
    {
        $reseller = Reseller::create(['name' => 'R', 'slug' => 'r']);
        $user = User::factory()->create();
        $reseller->users()->attach($user, ['role' => 'owner']);
        $this->organizer->update(['reseller_id' => $reseller->id]);

        $this->actingAs($user)->get(route('reseller.organizers.edit', [$reseller, $this->organizer]))->assertOk();
        $this->actingAs($user)->put(route('reseller.organizers.update', [$reseller, $this->organizer]), ['name' => 'X', 'is_active' => 1])->assertRedirect();
        $this->assertSame('X', $this->organizer->fresh()->name);
    }

    public function test_home_search_and_category_filter(): void
    {
        $this->event(['title' => 'Jazznacht', 'slug' => 'jazz', 'category' => 'konzert']);
        $this->event(['title' => 'Kochkurs', 'slug' => 'koch', 'category' => 'kurs']);

        $this->get('/?q=Jazz')->assertSee('Jazznacht')->assertDontSee('Kochkurs');
        $this->get('/?kategorie=kurs')->assertSee('Kochkurs')->assertDontSee('Jazznacht');
    }

    public function test_admin_sees_own_memberships_on_dashboard(): void
    {
        $this->organizer->users()->attach($this->admin, ['role' => 'owner']);
        $this->actingAs($this->admin)->get(route('dashboard', ['alle' => 1]))->assertOk()->assertSee('Verein A');
    }

    public function test_user_with_multiple_tenants_lands_in_chosen_dashboard_and_can_switch(): void
    {
        $second = Organizer::create(['name' => 'Verein B', 'slug' => 'verein-b']);
        $second->users()->attach($this->owner, ['role' => 'manager']);

        $this->actingAs($this->owner)->get(route('dashboard'))->assertRedirect(route('organizer.dashboard', $this->organizer));
        $this->actingAs($this->owner)->get(route('tenant.switch', ['organizer', $second->id]))->assertRedirect(route('organizer.dashboard', $second));
        $this->actingAs($this->owner)->get(route('dashboard'))->assertRedirect(route('organizer.dashboard', $second));
        $this->actingAs($this->owner)->get(route('dashboard', ['alle' => 1]))->assertOk()->assertSee('Verein A')->assertSee('Verein B');
        $this->actingAs($this->owner)->get(route('organizer.dashboard', $this->organizer))->assertOk()->assertSee('Mandant wechseln')->assertSee('Verein B');

        $foreign = Organizer::create(['name' => 'Fremd', 'slug' => 'fremd']);
        $this->actingAs($this->owner)->get(route('tenant.switch', ['organizer', $foreign->id]))->assertNotFound();
    }

    public function test_admin_assigns_and_removes_tenants(): void
    {
        $second = Organizer::create(['name' => 'Verein B', 'slug' => 'verein-b']);
        $reseller = Reseller::create(['name' => 'R', 'slug' => 'r']);

        $this->actingAs($this->admin)->post(route('admin.users.attach', $this->owner), ['type' => 'organizer', 'tenant_id' => $second->id, 'role' => 'cashier'])->assertRedirect();
        $this->actingAs($this->admin)->post(route('admin.users.attach', $this->owner), ['type' => 'reseller', 'tenant_id' => $reseller->id, 'role' => 'admin'])->assertRedirect();
        $this->assertSame(2, $this->owner->organizers()->count());
        $this->assertSame(1, $this->owner->resellers()->count());

        $this->actingAs($this->admin)->post(route('admin.users.attach', $this->owner), ['type' => 'organizer', 'tenant_id' => $second->id, 'role' => 'bogus'])->assertSessionHasErrors('role');
        $this->actingAs($this->admin)->get(route('admin.users.edit', $this->owner))->assertOk()->assertSee('Verein B');
        $this->actingAs($this->admin)->get(route('admin.users.index', ['rolle' => 'mehrere']))->assertOk();

        $this->actingAs($this->admin)->delete(route('admin.users.detach', [$this->owner, 'organizer', $second->id]))->assertRedirect();
        $this->assertSame(1, $this->owner->organizers()->count());
        $this->actingAs($this->owner)->post(route('admin.users.attach', $this->owner), ['type' => 'organizer', 'tenant_id' => $second->id, 'role' => 'owner'])->assertForbidden();
    }
}
