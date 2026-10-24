<?php

namespace App\Services\Push;

use Minishlink\WebPush\VAPID as WebPushVapid;

/** VAPID-Schlüssel: aus der .env, sonst automatisch erzeugt und in storage/app/vapid.json abgelegt. */
class Vapid
{
    public static function keys(): array
    {
        $public = config('tickets.vapid_public');
        $private = config('tickets.vapid_private');
        if ($public && $private) {
            return ['publicKey' => $public, 'privateKey' => $private];
        }

        $file = storage_path('app/vapid.json');
        if (is_file($file)) {
            $keys = json_decode((string) file_get_contents($file), true);
            if (! empty($keys['publicKey']) && ! empty($keys['privateKey'])) {
                return $keys;
            }
        }

        $keys = WebPushVapid::createVapidKeys();
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($keys), LOCK_EX);
        @chmod($file, 0600);

        return $keys;
    }

    public static function publicKey(): string
    {
        return self::keys()['publicKey'];
    }

    public static function subject(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return 'mailto:noreply@'.$host;
    }
}
