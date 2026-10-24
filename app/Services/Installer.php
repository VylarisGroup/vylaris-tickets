<?php

namespace App\Services;

use App\Models\User;
use App\Support\EnvFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use PDOException;
use RuntimeException;

/** Zustand und Hilfsfunktionen des Einrichtungsassistenten. */
class Installer
{
    private ?bool $memo = null;

    public function enabled(): bool
    {
        return (bool) config('tickets.installer_enabled');
    }

    public function env(): EnvFile
    {
        return new EnvFile(app()->environmentFilePath());
    }

    public function markerPath(): string
    {
        return storage_path('app/installed.json');
    }

    /** Solange diese Datei existiert, läuft die Einrichtung (ein vorhandener Admin allein bedeutet noch nicht «fertig»). */
    public function progressPath(): string
    {
        return storage_path('app/installing');
    }

    public function isInstalled(): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if ($this->memo !== null) {
            return $this->memo;
        }

        if (is_file($this->markerPath())) {
            return $this->memo = true;
        }

        if (is_file($this->progressPath())) {
            return $this->memo = false;
        }

        // Bereits manuell eingerichtete Installation (vor dem Assistenten): Admin vorhanden -> als fertig markieren.
        try {
            if (Schema::hasTable('users') && User::where('is_platform_admin', true)->exists()) {
                $this->markInstalled();

                return true;
            }
        } catch (\Throwable) {
            // keine Datenbank -> nicht installiert
        }

        return $this->memo = false;
    }

    public function markInProgress(): void
    {
        $this->ensureDir();
        @file_put_contents($this->progressPath(), now()->toIso8601String());
        $this->memo = null;
    }

    public function markInstalled(): void
    {
        $this->ensureDir();
        file_put_contents($this->markerPath(), json_encode(['installed_at' => now()->toIso8601String(), 'version' => config('tickets.version')]));
        @unlink($this->progressPath());
        $this->memo = true;
    }

    public function forget(): void
    {
        $this->memo = null;
    }

    public function adminExists(): bool
    {
        try {
            return Schema::hasTable('users') && User::where('is_platform_admin', true)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function databaseReady(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('events') && Schema::hasTable('migrations');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Systemvoraussetzungen.
     *
     * @return array<int,array{label:string,ok:bool,hard:bool,hint:?string}>
     */
    public function requirements(): array
    {
        $rows = [];
        $add = function (string $label, bool $ok, ?string $hint = null, bool $hard = true) use (&$rows) {
            $rows[] = ['label' => $label, 'ok' => $ok, 'hard' => $hard, 'hint' => $ok ? null : $hint];
        };

        $add('PHP '.PHP_VERSION.' (mindestens 8.3)', version_compare(PHP_VERSION, '8.3.0', '>='), 'Bitte in Plesk unter «PHP-Einstellungen» PHP 8.3 oder neuer wählen.');

        foreach (['pdo_mysql' => 'MySQL-Treiber', 'openssl' => 'OpenSSL', 'mbstring' => 'Multibyte-Strings', 'gd' => 'Grafik (für QR-Codes und Icons)', 'zip' => 'ZIP (für Updates)', 'curl' => 'cURL (Zahlungen, Updates)', 'fileinfo' => 'Dateitypen', 'xml' => 'XML', 'ctype' => 'Ctype', 'tokenizer' => 'Tokenizer'] as $ext => $name) {
            $add("PHP-Erweiterung {$ext} ({$name})", extension_loaded($ext), "Die Erweiterung «{$ext}» ist nicht aktiv. In Plesk unter «PHP-Einstellungen» aktivieren oder den Hoster bitten.");
        }

        $env = $this->env();
        $add('Datei .env beschreibbar', $env->writable(), 'Die Datei «.env» (bzw. der App-Ordner) muss für PHP beschreibbar sein, damit der Assistent die Einstellungen speichern kann.');
        $add('Ordner storage/ beschreibbar', is_writable(storage_path()) && is_writable(storage_path('framework')) && is_writable(storage_path('logs')), 'Schreibrechte für «storage» und alle Unterordner setzen (z. B. 775).');
        $add('Ordner bootstrap/cache beschreibbar', is_writable(base_path('bootstrap/cache')), 'Schreibrechte für «bootstrap/cache» setzen (z. B. 775).');
        $add('Gebaute Oberfläche vorhanden (public/build)', is_file(public_path('build/manifest.json')), 'Der Ordner «public/build» fehlt. Bitte die vollständige ZIP-Datei des Releases verwenden.');
        $add('Composer-Pakete vorhanden', is_file(base_path('vendor/autoload.php')), 'Der Ordner «vendor» fehlt. Bitte «composer install --no-dev» ausführen.');

        $limit = ini_get('memory_limit');
        $bytes = $limit === '-1' ? PHP_INT_MAX : $this->bytes((string) $limit);
        $add("Arbeitsspeicher-Limit {$limit} (empfohlen: 128M)", $bytes >= 128 * 1024 * 1024, 'Für PDF-Tickets empfehlen wir mindestens 128M («memory_limit»).', false);

        $secure = request()->isSecure() || str_starts_with((string) config('app.url'), 'https://');
        $add('Verschlüsselte Verbindung (HTTPS)', $secure, 'Ohne HTTPS funktionieren Kamera-Scanner, Installation als App und sichere Zahlungen nicht zuverlässig. Bitte ein Zertifikat (z. B. Let\'s Encrypt) aktivieren.', false);

        return $rows;
    }

    public function requirementsMet(): bool
    {
        return collect($this->requirements())->where('hard', true)->every(fn ($r) => $r['ok']);
    }

    /** Verbindet testen. Gibt bei Fehler eine verständliche Meldung samt Lösungsvorschlag zurück. */
    public function testConnection(array $db, bool $createIfMissing = false): ?string
    {
        try {
            $this->pdo($db);

            return null;
        } catch (PDOException $e) {
            if ($createIfMissing && ($e->getCode() === 1049 || str_contains($e->getMessage(), 'Unknown database'))) {
                try {
                    $this->createDatabase($db);
                    $this->pdo($db);

                    return null;
                } catch (PDOException $inner) {
                    return 'Die Datenbank «'.$db['database'].'» existiert nicht und konnte nicht automatisch angelegt werden ('.$this->explain($inner).'). Bitte lege sie in Plesk unter «Datenbanken» an.';
                }
            }

            return $this->explain($e);
        }
    }

    /** Wendet Datenbankzugang zur Laufzeit an (die .env wirkt erst ab der nächsten Anfrage). */
    public function applyConnection(array $db): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $db['host'],
            'database.connections.mysql.port' => $db['port'],
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'],
            'database.connections.mysql.password' => $db['password'],
        ]);
        DB::purge('mysql');
        $this->forget();
    }

    public function migrate(): void
    {
        $code = Artisan::call('migrate', ['--force' => true]);

        if ($code !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: 'Die Datenbank-Migration ist fehlgeschlagen.');
        }
    }

    public function explain(PDOException $e): string
    {
        $msg = $e->getMessage();
        $code = (int) ($e->errorInfo[1] ?? $e->getCode());

        return match (true) {
            str_contains($msg, 'could not find driver') => 'Der PHP-MySQL-Treiber (pdo_mysql) fehlt. Bitte in den PHP-Einstellungen aktivieren.',
            $code === 1045 || str_contains($msg, 'Access denied') => 'Benutzername oder Passwort stimmen nicht. Prüfe die Zugangsdaten des Datenbank-Benutzers (in Plesk unter «Datenbanken → Benutzerverwaltung»).',
            $code === 1049 || str_contains($msg, 'Unknown database') => 'Die Datenbank existiert nicht. Lege sie an oder aktiviere die Option «Datenbank automatisch anlegen».',
            str_contains($msg, 'getaddrinfo') || str_contains($msg, 'php_network_getaddresses') || str_contains($msg, 'Name or service not known') => 'Der Server-Name ist unbekannt. Bei Plesk ist der Host fast immer «localhost».',
            $code === 2002 || str_contains($msg, 'Connection refused') || str_contains($msg, 'No such file') => 'Der Datenbank-Server ist unter diesem Host/Port nicht erreichbar. Versuche «localhost» bzw. «127.0.0.1» und Port 3306. Bei einem externen Server muss die Verbindung freigegeben sein.',
            str_contains($msg, 'timed out') || $code === 2006 => 'Zeitüberschreitung beim Verbinden. Eine Firewall blockiert vermutlich den Zugriff.',
            default => 'Verbindung fehlgeschlagen: '.preg_replace('/\s+/', ' ', mb_substr($msg, 0, 200)),
        };
    }

    // ---------------------------------------------------------------------------------------------

    private function pdo(array $db, bool $withDatabase = true): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;%scharset=utf8mb4', $db['host'], $db['port'], $withDatabase ? 'dbname='.$db['database'].';' : '');

        return new PDO($dsn, $db['username'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    }

    private function createDatabase(array $db): void
    {
        $name = str_replace('`', '', $db['database']);
        $this->pdo($db, false)->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function ensureDir(): void
    {
        if (! is_dir(dirname($this->markerPath()))) {
            @mkdir(dirname($this->markerPath()), 0775, true);
        }
    }

    private function bytes(string $v): int
    {
        $n = (int) $v;

        return match (strtolower(substr(trim($v), -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
