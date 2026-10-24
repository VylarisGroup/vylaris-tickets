<?php

namespace Tests\Feature;

use App\Enums\OrganizerRole;
use App\Enums\ResellerRole;
use App\Models\Organizer;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    private function reseller(array $attrs = []): Reseller
    {
        return Reseller::create(array_merge([
            'name' => 'Muster Reseller', 'slug' => 'muster', 'domain' => 'tickets.muster.ch',
            'brand_name' => 'Muster Tickets', 'brand_color' => '#aa2200',
        ], $attrs));
    }

    private function organizer(?Reseller $reseller = null, string $name = 'Verein A'): Organizer
    {
        return Organizer::create(['reseller_id' => $reseller?->id, 'name' => $name, 'slug' => str($name)->slug()]);
    }

    public function test_default_branding_without_reseller(): void
    {
        $this->get('/')->assertOk()->assertSee('Vylaris Tickets')->assertSee('#1867F8', false);
    }

    public function test_reseller_domain_applies_whitelabel_branding(): void
    {
        $this->reseller();

        $this->get('http://tickets.muster.ch/')
            ->assertOk()->assertSee('Muster Tickets')->assertSee('#aa2200', false)->assertDontSee('Vylaris Tickets');
    }

    public function test_inactive_reseller_domain_is_not_served(): void
    {
        $this->reseller(['is_active' => false]);

        $this->get('http://tickets.muster.ch/')->assertNotFound();
    }

    public function test_registration_creates_organizer_under_current_reseller(): void
    {
        $reseller = $this->reseller();

        $this->post('http://tickets.muster.ch/registrieren', [
            'name' => 'Anna', 'email' => 'anna@example.ch', 'password' => 'geheim-passwort-1', 'password_confirmation' => 'geheim-passwort-1',
            'organizer_name' => 'Turnverein Heiden',
        ])->assertRedirect(route('dashboard'));

        $organizer = Organizer::where('slug', 'turnverein-heiden')->firstOrFail();
        $this->assertSame($reseller->id, $organizer->reseller_id);
        $this->assertSame(OrganizerRole::Owner, User::where('email', 'anna@example.ch')->first()->organizerRole($organizer));
    }

    public function test_registration_can_be_closed_per_reseller(): void
    {
        $this->reseller(['allow_registration' => false]);

        $this->get('http://tickets.muster.ch/registrieren')->assertNotFound();
    }

    public function test_registration_respects_organizer_limit(): void
    {
        $reseller = $this->reseller(['max_organizers' => 1]);
        $this->organizer($reseller);

        $this->post('http://tickets.muster.ch/registrieren', [
            'name' => 'Ben', 'email' => 'ben@example.ch', 'password' => 'geheim-passwort-1', 'password_confirmation' => 'geheim-passwort-1',
            'organizer_name' => 'Zweiter Verein',
        ])->assertSessionHasErrors('organizer_name');

        $this->assertDatabaseMissing('users', ['email' => 'ben@example.ch']);
    }

    public function test_admin_area_requires_platform_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->get('/admin')->assertOk();
    }

    public function test_reseller_member_manages_own_organizers_only(): void
    {
        $mine = $this->reseller();
        $other = $this->reseller(['name' => 'Fremd', 'slug' => 'fremd', 'domain' => 'tickets.fremd.ch']);
        $user = User::factory()->create();
        $mine->users()->attach($user, ['role' => ResellerRole::Admin->value]);

        $own = $this->organizer($mine, 'Eigener Verein');
        $foreign = $this->organizer($other, 'Fremder Verein');

        $this->actingAs($user)->get(route('reseller.dashboard', $mine))->assertOk();
        $this->actingAs($user)->get(route('reseller.dashboard', $other))->assertForbidden();
        $this->actingAs($user)->get(route('organizer.settings.edit', $own))->assertOk();
        $this->actingAs($user)->get(route('organizer.settings.edit', $foreign))->assertForbidden();
    }

    public function test_reseller_cannot_exceed_organizer_limit(): void
    {
        $reseller = $this->reseller(['max_organizers' => 1]);
        $user = User::factory()->create();
        $reseller->users()->attach($user, ['role' => ResellerRole::Owner->value]);

        $payload = fn (string $name) => ['name' => $name, 'owner_name' => 'Ina', 'owner_email' => 'ina@example.ch', 'owner_password' => 'geheim-passwort-1'];

        $this->actingAs($user)->post(route('reseller.organizers.store', $reseller), $payload('Erster'))->assertRedirect();
        $this->actingAs($user)->post(route('reseller.organizers.store', $reseller), $payload('Zweiter'))->assertSessionHasErrors('name');

        $this->assertSame(1, $reseller->organizers()->count());
    }

    public function test_organizer_roles_limit_access(): void
    {
        $organizer = $this->organizer();
        [$owner, $cashier, $scanner, $stranger] = User::factory()->count(4)->create();
        $organizer->users()->attach($owner, ['role' => 'owner']);
        $organizer->users()->attach($cashier, ['role' => 'cashier']);
        $organizer->users()->attach($scanner, ['role' => 'scanner']);

        $this->actingAs($owner)->get(route('organizer.members.index', $organizer))->assertOk();
        $this->actingAs($cashier)->get(route('organizer.dashboard', $organizer))->assertOk();
        $this->actingAs($cashier)->get(route('organizer.members.index', $organizer))->assertForbidden();
        $this->actingAs($scanner)->get(route('organizer.settings.edit', $organizer))->assertForbidden();
        $this->actingAs($stranger)->get(route('organizer.dashboard', $organizer))->assertForbidden();
    }

    public function test_inactive_organizer_is_locked_for_its_own_team(): void
    {
        $organizer = $this->organizer();
        $organizer->update(['is_active' => false]);
        $owner = User::factory()->create();
        $organizer->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner)->get(route('organizer.dashboard', $organizer))->assertForbidden();
        $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->get(route('organizer.dashboard', $organizer))->assertOk();
    }

    public function test_last_owner_cannot_be_removed_or_demoted(): void
    {
        $organizer = $this->organizer();
        $owner = User::factory()->create();
        $organizer->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner)->delete(route('organizer.members.destroy', [$organizer, $owner]))->assertSessionHasErrors('role');
        $this->actingAs($owner)->put(route('organizer.members.update', [$organizer, $owner]), ['role' => 'scanner'])->assertSessionHasErrors('role');

        $this->assertSame(OrganizerRole::Owner, $owner->organizerRole($organizer));
    }

    public function test_payment_keys_are_stored_encrypted(): void
    {
        $organizer = $this->organizer();
        $owner = User::factory()->create();
        $organizer->users()->attach($owner, ['role' => 'owner']);

        $this->actingAs($owner)->put(route('organizer.settings.update', $organizer), [
            'name' => 'Verein A', 'country' => 'ch', 'default_currency' => 'EUR', 'mollie_api_key' => 'live_geheim123',
        ])->assertRedirect();

        $raw = \DB::table('organizers')->where('id', $organizer->id)->value('payment_settings');
        $this->assertStringNotContainsString('live_geheim123', $raw);
        $this->assertSame('live_geheim123', $organizer->fresh()->payment_settings['mollie_api_key']);
        $this->assertSame('EUR', $organizer->fresh()->default_currency);

        // Leeres Feld behält den Schlüssel.
        $this->actingAs($owner)->put(route('organizer.settings.update', $organizer), [
            'name' => 'Verein A', 'country' => 'CH', 'default_currency' => 'CHF', 'mollie_api_key' => '',
        ]);
        $this->assertSame('live_geheim123', $organizer->fresh()->payment_settings['mollie_api_key']);
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('tickets:create-admin', ['email' => 'root@example.ch', 'name' => 'Root', '--password' => 'sehr-langes-passwort'])->assertSuccessful();

        $this->assertTrue(User::where('email', 'root@example.ch')->first()->is_platform_admin);
    }
}
