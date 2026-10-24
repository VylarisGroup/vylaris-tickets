<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\Reseller;
use App\Models\ResellerDomain;
use App\Models\User;
use App\Services\Dns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainTest extends TestCase
{
    use RefreshDatabase;

    private Reseller $reseller;

    private User $user;

    /** @var array<string,string[]> */
    private array $cname = [];

    /** @var array<string,string[]> */
    private array $a = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['tickets.base_domain' => 'tickets.vylaris.ch', 'tickets.cname_target' => null]);

        $this->reseller = Reseller::create(['name' => 'Eventhaus', 'slug' => 'eventhaus', 'brand_color' => '#e11d48']);
        $this->user = User::factory()->create();
        $this->reseller->users()->attach($this->user, ['role' => 'owner']);

        $fake = new class($this) extends Dns
        {
            public function __construct(private DomainTest $t) {}

            public function cname(string $host): array
            {
                return $this->t->records('cname', $host);
            }

            public function a(string $host): array
            {
                return $this->t->records('a', $host);
            }
        };
        $this->app->instance(Dns::class, $fake);
    }

    public function records(string $type, string $host): array
    {
        return ($type === 'cname' ? $this->cname : $this->a)[$host] ?? [];
    }

    private function add(string $domain, ?User $as = null, ?Reseller $reseller = null)
    {
        return $this->actingAs($as ?? $this->user)->post(route('reseller.domains.store', $reseller ?? $this->reseller), ['domain' => $domain]);
    }

    public function test_domain_is_pending_until_cname_points_to_the_platform(): void
    {
        $this->add('Tickets.Eventhaus.ch')->assertRedirect();

        $domain = ResellerDomain::firstOrFail();
        $this->assertSame('tickets.eventhaus.ch', $domain->domain);
        $this->assertFalse($domain->isVerified());
        $this->assertStringContainsString('noch kein CNAME', $domain->last_error);

        // Brandet noch nicht
        Organizer::create(['name' => 'V', 'slug' => 'v', 'reseller_id' => $this->reseller->id]);
        $this->get('http://tickets.eventhaus.ch/')->assertOk()->assertDontSee('Eventhaus');

        $this->cname['tickets.eventhaus.ch'] = ['tickets.vylaris.ch'];
        $this->actingAs($this->user)->post(route('reseller.domains.verify', [$this->reseller, $domain]))->assertSessionHas('status');

        $this->assertTrue($domain->fresh()->isVerified());
        $this->get('http://tickets.eventhaus.ch/manifest.webmanifest')->assertJsonPath('name', 'Eventhaus');
    }

    public function test_wrong_cname_target_shows_helpful_error(): void
    {
        $this->cname['tickets.eventhaus.ch'] = ['other.example.com'];
        $this->add('tickets.eventhaus.ch');

        $domain = ResellerDomain::firstOrFail();
        $this->assertFalse($domain->isVerified());
        $this->assertStringContainsString('other.example.com', $domain->last_error);
    }

    public function test_apex_domain_can_be_verified_via_matching_a_record(): void
    {
        $this->a['tickets.vylaris.ch'] = ['203.0.113.10'];
        $this->a['eventhaus.ch'] = ['203.0.113.10'];

        $this->add('eventhaus.ch');

        $this->assertTrue(ResellerDomain::firstOrFail()->isVerified());
    }

    public function test_a_record_pointing_elsewhere_does_not_verify(): void
    {
        $this->a['tickets.vylaris.ch'] = ['203.0.113.10'];
        $this->a['eventhaus.ch'] = ['198.51.100.7'];

        $this->add('eventhaus.ch');

        $this->assertFalse(ResellerDomain::firstOrFail()->isVerified());
    }

    public function test_verified_domain_cannot_be_claimed_by_another_reseller(): void
    {
        $this->cname['tickets.eventhaus.ch'] = ['tickets.vylaris.ch'];
        $this->add('tickets.eventhaus.ch');

        $other = Reseller::create(['name' => 'Fremd', 'slug' => 'fremd']);
        $stranger = User::factory()->create();
        $other->users()->attach($stranger, ['role' => 'owner']);

        $this->add('tickets.eventhaus.ch', $stranger, $other)->assertSessionHasErrors('domain');
        $this->assertSame(0, $other->domains()->count());
    }

    public function test_pending_claims_cannot_hijack_before_verification_and_only_first_verifier_wins(): void
    {
        $other = Reseller::create(['name' => 'Fremd', 'slug' => 'fremd']);
        $stranger = User::factory()->create();
        $other->users()->attach($stranger, ['role' => 'owner']);

        // Beide beanspruchen die Domain, ohne dass sie auf uns zeigt: nichts wird gebrandet.
        $this->add('tickets.eventhaus.ch');
        $this->add('tickets.eventhaus.ch', $stranger, $other);
        $this->get('http://tickets.eventhaus.ch/')->assertOk()->assertDontSee('Fremd');

        $this->cname['tickets.eventhaus.ch'] = ['tickets.vylaris.ch'];
        [$confirmed] = app(\App\Services\DomainVerifier::class)->sweep();

        $this->assertSame(1, $confirmed, 'Nur ein Reseller kann dieselbe Domain bestätigen');
        $this->assertSame(1, ResellerDomain::whereNotNull('verified_at')->count());
    }

    public function test_platform_domains_and_invalid_input_are_rejected(): void
    {
        foreach (['tickets.vylaris.ch', 'shop.tickets.vylaris.ch', 'localhost', 'no spaces.ch', '192.168.0.1', 'a'] as $bad) {
            $this->add($bad)->assertSessionHasErrors('domain');
        }

        $this->assertSame(0, ResellerDomain::count());
    }

    public function test_url_input_is_normalized(): void
    {
        $this->add('https://Tickets.Eventhaus.ch/shop?x=1');

        $this->assertSame('tickets.eventhaus.ch', ResellerDomain::firstOrFail()->domain);
    }

    public function test_limit_per_reseller(): void
    {
        config(['tickets.max_domains_per_reseller' => 2]);

        $this->add('a.eventhaus.ch');
        $this->add('b.eventhaus.ch');
        $this->add('c.eventhaus.ch')->assertSessionHasErrors('domain');
    }

    public function test_only_own_domains_can_be_managed_and_only_by_reseller_members(): void
    {
        $this->add('a.eventhaus.ch');
        $domain = ResellerDomain::firstOrFail();

        $other = Reseller::create(['name' => 'Fremd', 'slug' => 'fremd']);
        $stranger = User::factory()->create();
        $other->users()->attach($stranger, ['role' => 'owner']);

        $this->actingAs($stranger)->delete(route('reseller.domains.destroy', [$other, $domain]))->assertNotFound();
        $this->actingAs($stranger)->get(route('reseller.domains.index', $this->reseller))->assertForbidden();

        $this->actingAs($this->user)->delete(route('reseller.domains.destroy', [$this->reseller, $domain]))->assertRedirect();
        $this->assertSame(0, ResellerDomain::count());
    }

    public function test_domains_page_renders_with_instructions(): void
    {
        $this->actingAs($this->user)->get(route('reseller.domains.index', $this->reseller))->assertOk()->assertSee('tickets.vylaris.ch')->assertSee('CNAME');
    }

    public function test_sweep_removes_stale_pending_domains(): void
    {
        $this->add('old.eventhaus.ch');
        ResellerDomain::query()->update(['created_at' => now()->subDays(15)]);

        $this->artisan('tickets:verify-domains')->assertSuccessful();

        $this->assertSame(0, ResellerDomain::count());
    }

    public function test_domain_check_endpoint_for_on_demand_tls(): void
    {
        $this->cname['tickets.eventhaus.ch'] = ['tickets.vylaris.ch'];
        $this->add('tickets.eventhaus.ch');

        $this->get('/domain-check?domain=tickets.eventhaus.ch')->assertOk();
        $this->get('/domain-check?domain=eventhaus.tickets.vylaris.ch')->assertOk();
        $this->get('/domain-check?domain=unknown.example.com')->assertNotFound();

        config(['tickets.domain_check_token' => 'geheim']);
        $this->get('/domain-check?domain=tickets.eventhaus.ch')->assertForbidden();
        $this->get('/domain-check?domain=tickets.eventhaus.ch&token=geheim')->assertOk();
    }

    public function test_inactive_reseller_domain_is_not_served(): void
    {
        $this->cname['tickets.eventhaus.ch'] = ['tickets.vylaris.ch'];
        $this->add('tickets.eventhaus.ch');
        $this->reseller->update(['is_active' => false]);

        $this->get('http://tickets.eventhaus.ch/')->assertNotFound();
    }

    public function test_reseller_domain_page_shows_guide_and_records_for_pending_domains(): void
    {
        $this->a['tickets.vylaris.ch'] = ['203.0.113.10'];
        $this->add('tickets.eventhaus.ch');

        $this->actingAs($this->user)->get(route('reseller.domains.index', $this->reseller))
            ->assertOk()
            ->assertSee('So richtest du deine Domain ein')
            ->assertSee('Benötigte DNS-Einträge für tickets.eventhaus.ch')
            ->assertSee('Aktueller DNS-Stand');
    }

    public function test_domain_guide_builds_records_for_subdomain_and_apex(): void
    {
        $this->a['tickets.vylaris.ch'] = ['203.0.113.10'];
        $guide = $this->app->make(\App\Services\DomainGuide::class);

        $this->assertTrue($guide->isApex('eventhaus.ch'));
        $this->assertTrue($guide->isApex('eventhaus.co.uk'));
        $this->assertFalse($guide->isApex('tickets.eventhaus.ch'));
        $this->assertSame('tickets', $guide->host('tickets.eventhaus.ch'));
        $this->assertSame('@', $guide->host('eventhaus.ch'));

        $sub = $guide->records('tickets.eventhaus.ch');
        $this->assertCount(1, $sub);
        $this->assertSame('CNAME', $sub[0]['type']);
        $this->assertSame('tickets.vylaris.ch', $sub[0]['value']);

        $apex = $guide->records('eventhaus.ch');
        $this->assertContains('A', array_column($apex, 'type'));
        $this->assertContains('203.0.113.10', array_column($apex, 'value'));
    }

    public function test_admin_domain_guide_is_only_for_platform_admins(): void
    {
        $this->actingAs($this->user)->get(route('admin.domains.guide'))->assertForbidden();

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin)->get(route('admin.domains.guide'))
            ->assertOk()->assertSee('Selbsttest')->assertSee('Wildcard-DNS')->assertSee('Eventhaus');
    }

    public function test_plesk_sync_creates_alias_and_certificate_for_verified_domains(): void
    {
        config(['tickets.plesk.key' => 'k', 'tickets.plesk.domain' => 'events.vylaris.ch', 'tickets.plesk.url' => 'https://127.0.0.1:8443', 'tickets.plesk.email' => 'a@vylaris.ch']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['code' => 0, 'stdout' => 'ok', 'stderr' => ''])]);

        $ok = $this->reseller->domains()->create(['domain' => 'tickets.eventhaus.ch', 'verified_at' => now(), 'verified_key' => 'tickets.eventhaus.ch']);
        $pending = $this->reseller->domains()->create(['domain' => 'wartet.eventhaus.ch']);

        $this->artisan('tickets:sync-plesk')->assertSuccessful();

        $this->assertNotNull($ok->fresh()->plesk_alias_at);
        $this->assertNotNull($ok->fresh()->plesk_cert_at);
        $this->assertNull($pending->fresh()->plesk_alias_at);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v2/cli/site_alias/call') && in_array('tickets.eventhaus.ch', $r['params']) && $r->hasHeader('X-API-Key', 'k'));
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->url(), '/cli/extension/call') && in_array('letsencrypt', $r['params']) && in_array('tickets.eventhaus.ch', $r['params']));
    }

    public function test_plesk_sync_records_errors_and_retries(): void
    {
        config(['tickets.plesk.key' => 'k', 'tickets.plesk.domain' => 'events.vylaris.ch']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['code' => 1, 'stdout' => '', 'stderr' => 'kaputt'])]);

        $d = $this->reseller->domains()->create(['domain' => 'tickets.eventhaus.ch', 'verified_at' => now(), 'verified_key' => 'tickets.eventhaus.ch']);
        $this->artisan('tickets:sync-plesk')->assertSuccessful();

        $this->assertNull($d->fresh()->plesk_alias_at);
        $this->assertStringContainsString('kaputt', $d->fresh()->plesk_error);
    }

    public function test_plesk_sync_does_nothing_without_configuration(): void
    {
        config(['tickets.plesk.key' => null]);
        \Illuminate\Support\Facades\Http::fake();
        $this->artisan('tickets:sync-plesk')->assertSuccessful();
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }
}
