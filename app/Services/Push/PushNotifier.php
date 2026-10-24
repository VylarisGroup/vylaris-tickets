<?php

namespace App\Services\Push;

use App\Models\Organizer;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Collection;

/** Verschickt Push-Benachrichtigungen an Teammitglieder bzw. Plattform-Admins gemäss deren Einstellungen. */
class PushNotifier
{
    public function __construct(private PushTransport $transport) {}

    /** Inhaber und Verwalter eines Veranstalters. */
    public function toOrganizer(Organizer $organizer, string $category, string $title, string $body, string $url, ?string $tag = null): void
    {
        $users = $organizer->users()->wherePivotIn('role', ['owner', 'manager'])->get();

        $this->toUsers($users, $category, $title, $body, $url, $tag);
    }

    public function toAdmins(string $category, string $title, string $body, string $url, ?string $tag = null): void
    {
        $this->toUsers(User::where('is_platform_admin', true)->get(), $category, $title, $body, $url, $tag);
    }

    public function toUsers(iterable $users, string $category, string $title, string $body, string $url, ?string $tag = null): void
    {
        $users = Collection::make($users)->filter(fn (User $u) => $u->wantsPush($category))->unique('id');
        if ($users->isEmpty()) {
            return;
        }

        $payload = ['title' => $title, 'body' => $body, 'url' => $url, 'tag' => $tag ?? $category];
        $job = fn () => $this->deliver($users->pluck('id')->all(), $payload);

        // Im Web-Request erst nach der Antwort senden, damit Kunden nicht auf Push-Server warten.
        app()->runningInConsole() ? $job() : app()->terminating($job);
    }

    /** Test-Nachricht an alle Geräte eines Nutzers. Gibt die Anzahl erreichter Geräte zurück. */
    public function test(User $user): int
    {
        return $this->deliver([$user->id], [
            'title' => 'Test-Benachrichtigung', 'body' => 'Push funktioniert auf diesem Gerät.', 'url' => '/konto/benachrichtigungen', 'tag' => 'test',
        ]);
    }

    private function deliver(array $userIds, array $payload): int
    {
        $sent = 0;

        try {
            PushSubscription::whereIn('user_id', $userIds)->each(function (PushSubscription $sub) use ($payload, &$sent) {
                try {
                    if ($this->transport->send($sub, $payload)) {
                        $sub->forceFill(['last_used_at' => now()])->save();
                        $sent++;
                    } else {
                        $sub->delete();
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }

        return $sent;
    }
}
