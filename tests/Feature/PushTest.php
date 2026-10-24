<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Push\PushTransport;
use App\Services\Push\Vapid;
use App\Services\Updater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakePushTransport implements PushTransport
{
    public array $sent = [];

    public bool $alive = true;

    public function send(PushSubscription $subscription, array $payload): bool
    {
        $this->sent[] = ['user' => $subscription->user_id, 'payload' => $payload];

        return $this->alive;
    }

    public function titles(?int $user = null): array
    {
        return array_values(array_map(fn ($s) => $s['payload']['title'], array_filter($this->sent, fn ($s) => $user === null || $s['user'] === $user)));
    }
}

class PushTest extends TestCase
{
    use RefreshDatabase;

    private FakePushTransport $push;

    private Organizer $organizer;

    private User $owner;

    private User $manager;

    private User $cashier;

    private User $admin;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->push = new FakePushTransport;
        $this->app->instance(PushTransport::class, $this->push);

        $this->organizer = Organizer::create(['name' => 'Verein A', 'slug' => 'verein-a', 'iban' => 'CH9300762011623852957']);
        [$this->owner, $this->manager, $this->cashier, $this->admin] = User::factory()->count(4)->create()->all();
        $this->organizer->users()->attach($this->owner, ['role' => 'owner']);
        $this->organizer->users()->attach($this->manager, ['role' => 'manager']);
        $this->organizer->users()->attach($this->cashier, ['role' => 'cashier']);
        $this->admin->forceFill(['is_platform_admin' => true])->save();

        foreach ([$this->owner, $this->manager, $this->cashier, $this->admin] as $u) {
            $this->subscribe($u);
        }

        $this->event = $this->organizer->events()->create([
            'title' => 'Fest', 'slug' => 'fest', 'type' => 'onsite', 'venue_name' => 'Halle', 'starts_at' => now()->addWeek(), 'currency' => 'CHF', 'status' => 'published', 'door_sales_enabled' => true,
        ]);
        $this->event->ticketTypes()->create(['name' => 'Standard', 'price_cents' => 2000, 'quota' => 2, 'max_per_order' => 5, 'sort' => 1, 'is_active' => true]);
        $this->event->ticketTypes()->create(['name' => 'Gratis', 'price_cents' => 0, 'max_per_order' => 5, 'sort' => 2, 'is_active' => true]);
    }

    private function subscribe(User $u): void
    {
        $u->pushSubscriptions()->create(['endpoint_hash' => hash('sha256', 'https://push.example/'.$u->id), 'endpoint' => 'https://push.example/'.$u->id, 'p256dh' => 'p', 'auth' => 'a']);
    }

    private function order(string $method, string $channel = 'online', int $typeIndex = 0, int $qty = 1)
    {
        $type = $this->event->ticketTypes()->get()[$typeIndex];

        return app(OrderService::class)->place($this->event->fresh(), [$type->id => $qty], [], ['name' => 'Kim Muster', 'email' => 'k@example.ch'], null, $method, $channel, $channel === 'door' ? $this->cashier : null);
    }

    public function test_pages_render_and_admin_sees_admin_categories(): void
    {
        $this->actingAs($this->owner)->get(route('push.edit'))->assertOk()->assertSee('Neue Bestellung')->assertDontSee('Neue Version');
        $this->actingAs($this->admin)->get(route('push.edit'))->assertOk()->assertSee('Neue Version')->assertSee('Neue Meldung');
        $this->actingAs($this->owner)->get(route('organizer.dashboard', $this->organizer))->assertOk()->assertSee('Benachrichtigungen');
    }

    public function test_device_subscribe_upsert_and_unsubscribe(): void
    {
        $user = User::factory()->create();
        $payload = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'BKey', 'auth' => 'authkey'], 'contentEncoding' => 'aes128gcm'];

        $this->actingAs($user)->postJson(route('push.subscribe'), $payload)->assertOk();
        $this->actingAs($user)->postJson(route('push.subscribe'), $payload)->assertOk();
        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->actingAs($user)->postJson(route('push.subscribe'), ['endpoint' => 'http://unsicher.example/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertStatus(422);

        $this->actingAs($user)->deleteJson(route('push.unsubscribe'), ['endpoint' => $payload['endpoint']])->assertOk();
        $this->assertSame(0, $user->pushSubscriptions()->count());
        auth()->logout();
        $this->postJson(route('push.subscribe'), $payload)->assertUnauthorized();
    }

    public function test_user_can_remove_only_own_device(): void
    {
        $mine = $this->owner->pushSubscriptions()->first();
        $theirs = $this->manager->pushSubscriptions()->first();

        $this->actingAs($this->owner)->delete(route('push.destroy', $theirs->id))->assertRedirect();
        $this->assertNotNull(PushSubscription::find($theirs->id));
        $this->actingAs($this->owner)->delete(route('push.destroy', $mine->id))->assertRedirect();
        $this->assertNull(PushSubscription::find($mine->id));
    }

    public function test_online_orders_notify_owner_and_manager_only(): void
    {
        $this->order('bank');

        $this->assertSame(['Überweisung wartet · Verein A'], $this->push->titles($this->owner->id));
        $this->assertSame(['Überweisung wartet · Verein A'], $this->push->titles($this->manager->id));
        $this->assertSame([], $this->push->titles($this->cashier->id));
        $this->assertSame([], $this->push->titles($this->admin->id));
        $this->assertStringContainsString('Kim Muster', $this->push->sent[0]['payload']['body']);
        $this->assertStringStartsWith('/veranstalter/verein-a/events/fest/bestellungen/', $this->push->sent[0]['payload']['url']);
    }

    public function test_free_online_order_counts_as_paid_but_door_sales_and_manual_payment_do_not_notify(): void
    {
        $this->order('cash', 'online', 1);
        $this->assertSame(['Neue Bestellung · Verein A'], $this->push->titles($this->owner->id));

        $this->push->sent = [];
        $this->order('cash', 'door', 1);
        $this->assertSame([], $this->push->sent);

        $bank = $this->order('bank');
        $this->push->sent = [];
        app(OrderService::class)->markPaid($bank, 'bank');
        $this->assertSame([], $this->push->sent);
    }

    public function test_sold_out_ticket_type_is_announced_once(): void
    {
        $this->order('bank', 'online', 0, 2);

        $this->assertContains('Ausverkauft · Verein A', $this->push->titles($this->owner->id));
        $this->assertCount(1, array_keys(array_filter($this->push->titles($this->owner->id), fn ($t) => str_starts_with($t, 'Ausverkauft'))));
    }

    public function test_preferences_switch_categories_off(): void
    {
        $this->actingAs($this->owner)->put(route('push.update'), ['prefs' => ['order_pending' => '1', 'sold_out' => '1']])->assertRedirect();
        $this->assertFalse($this->owner->fresh()->wantsPush('waitlist'));
        $this->assertTrue($this->owner->fresh()->wantsPush('order_pending'));

        $this->actingAs($this->manager)->put(route('push.update'), ['prefs' => ['sold_out' => '1']]);
        $this->order('bank');

        $this->assertSame(['Überweisung wartet · Verein A'], $this->push->titles($this->owner->id));
        $this->assertSame([], $this->push->titles($this->manager->id));
        $this->assertTrue((new User)->wantsPush('order_paid')); // Standard: alles an
    }

    public function test_waitlist_and_report_notify_the_right_people(): void
    {
        $url = route('shop.waitlist', [$this->organizer, $this->event]);
        $type = $this->event->ticketTypes()->first();
        $this->post($url, ['name' => 'Wart', 'email' => 'w@example.ch', 'ticket_type_id' => $type->id, 'quantity' => 2])->assertRedirect();
        $this->post($url, ['name' => 'Wart', 'email' => 'w@example.ch', 'ticket_type_id' => $type->id, 'quantity' => 2])->assertRedirect();

        $this->assertSame(['Neue Warteliste-Anmeldung · Verein A'], $this->push->titles($this->owner->id));

        $this->post(route('shop.report', [$this->organizer, $this->event]), ['reason' => 'spam'])->assertRedirect();
        $this->assertSame(['Neue Meldung'], $this->push->titles($this->admin->id));
        $this->assertSame(1, count(array_filter($this->push->titles($this->owner->id), fn ($t) => $t === 'Neue Meldung')) + 1);
    }

    public function test_test_push_and_expired_devices_are_cleaned_up(): void
    {
        $this->actingAs($this->owner)->post(route('push.test'))->assertRedirect()->assertSessionHas('status');
        $this->assertSame(['Test-Benachrichtigung'], $this->push->titles($this->owner->id));

        $this->push->alive = false;
        $this->actingAs($this->owner)->post(route('push.test'));
        $this->assertSame(0, $this->owner->pushSubscriptions()->count());

        $this->actingAs($this->owner)->post(route('push.test'))->assertSessionHas('error_status');
    }

    public function test_update_check_notifies_admins_once_per_version(): void
    {
        $this->mock(Updater::class, fn ($m) => $m->shouldReceive('latest')->andReturn(['version' => '99.0.0', 'newer' => true]));

        $this->artisan('tickets:check-updates')->assertSuccessful();
        $this->artisan('tickets:check-updates')->assertSuccessful();

        $this->assertSame(['Neue Version 99.0.0'], $this->push->titles($this->admin->id));
        $this->assertSame([], $this->push->titles($this->owner->id));
    }

    public function test_vapid_keys_are_generated_and_persisted(): void
    {
        config(['tickets.vapid_public' => null, 'tickets.vapid_private' => null]);
        $file = storage_path('app/vapid.json');
        $backup = is_file($file) ? file_get_contents($file) : null;
        @unlink($file);

        $public = Vapid::publicKey();
        $this->assertGreaterThan(60, strlen($public));
        $this->assertSame($public, Vapid::publicKey());
        $this->assertFileExists($file);

        $backup === null ? @unlink($file) : file_put_contents($file, $backup);
    }

    public function test_service_worker_handles_push(): void
    {
        $this->get('/sw.js')->assertOk()->assertSee("addEventListener('push'", false)->assertSee('notificationclick', false);
    }
}
