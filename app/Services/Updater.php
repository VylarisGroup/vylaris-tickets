<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

/**
 * Update-System: lädt das Release-Paket aus dem öffentlichen Repository von Vylaris Tickets,
 * sichert die aktuelle Installation, aktualisiert im Wartungsmodus und rollt bei Fehlern zurück.
 *
 * Die Quelle ist bewusst fest im Code verankert und nicht konfigurierbar.
 */
class Updater
{
    public const REPOSITORY = 'VylarisGroup/vylaris-tickets';

    private const ALLOWED_HOSTS = ['github.com', 'api.github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'];

    /** Verzeichnisse und Dateien, die ein Update ersetzt. Alles andere (.env, storage, uploads) bleibt unberührt. */
    private const MANAGED = ['app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'vendor', 'public/build', 'public/index.php', 'public/.htaccess', 'artisan', 'composer.json', 'composer.lock', 'package.json', 'VERSION'];

    /** Teilpfade, die beim Spiegeln nie überschrieben oder gelöscht werden. */
    private const PRESERVE = ['bootstrap/cache'];

    private string $base;

    public function __construct(?string $basePath = null)
    {
        $this->base = rtrim($basePath ?? base_path(), '/');
    }

    public function currentVersion(): string
    {
        $file = $this->base.'/VERSION';

        return is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
    }

    /**
     * Fragt das neueste Release ab.
     *
     * @return array{version:string,notes:string,url:string,asset_url:?string,sha256:?string,newer:bool}
     */
    public function latest(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('tickets.update.latest');
        }

        $release = Cache::remember('tickets.update.latest', 3600, function () {
            $response = Http::acceptJson()->withHeaders(['User-Agent' => 'vylaris-tickets-updater'])->timeout(15)
                ->get('https://api.github.com/repos/'.self::REPOSITORY.'/releases/latest');

            if (! $response->ok()) {
                throw new RuntimeException('Die Update-Quelle ist nicht erreichbar (HTTP '.$response->status().').');
            }

            $release = $response->json();
            $version = ltrim((string) ($release['tag_name'] ?? ''), 'vV');

            if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
                throw new RuntimeException('Die neueste Version hat ein ungültiges Format.');
            }

            $asset = collect($release['assets'] ?? [])->first(fn ($a) => preg_match('/^vylaris-tickets-[\d.]+\.zip$/', $a['name'] ?? ''));
            $sha = null;

            if ($asset) {
                $sha = isset($asset['digest']) && str_starts_with($asset['digest'], 'sha256:') ? substr($asset['digest'], 7) : null;
                $shaAsset = collect($release['assets'])->first(fn ($a) => ($a['name'] ?? '') === $asset['name'].'.sha256');

                if (! $sha && $shaAsset) {
                    $this->assertAllowedUrl($shaAsset['browser_download_url']);
                    $sha = strtolower(substr(trim(Http::withHeaders(['User-Agent' => 'vylaris-tickets-updater'])->timeout(15)->get($shaAsset['browser_download_url'])->body()), 0, 64));
                }
            }

            return [
                'version' => $version,
                'notes' => (string) ($release['body'] ?? ''),
                'url' => (string) ($release['html_url'] ?? ''),
                'asset_url' => $asset['browser_download_url'] ?? null,
                'sha256' => $sha,
            ];
        });

        return $this->withNewer($release);
    }

    /** Gibt die gecachte Prüfung zurück, ohne das Netzwerk zu verwenden (z. B. für Hinweise im Dashboard). */
    public function cachedLatest(): ?array
    {
        $cached = Cache::get('tickets.update.latest');

        return $cached ? $this->withNewer($cached) : null;
    }

    public function status(): ?array
    {
        $file = $this->base.'/storage/app/update-status.json';

        return is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    }

    /**
     * Installiert die angegebene Version. Wirft bei Fehlern, nachdem zurückgerollt wurde.
     */
    public function install(array $release): void
    {
        $version = $release['version'];

        if (! version_compare($version, $this->currentVersion(), '>')) {
            throw new RuntimeException('Es ist keine neuere Version verfügbar.');
        }
        if (empty($release['asset_url'])) {
            throw new RuntimeException('Das Release enthält kein Installationspaket.');
        }
        if (empty($release['sha256'])) {
            throw new RuntimeException('Für das Installationspaket liegt keine Prüfsumme vor. Das Update wird aus Sicherheitsgründen nicht installiert.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Die PHP-Erweiterung zip fehlt.');
        }
        foreach (['', '/storage/app', '/app', '/vendor'] as $dir) {
            if (! is_writable($this->base.$dir)) {
                throw new RuntimeException('Das Verzeichnis «'.($dir ?: '/').'» ist nicht beschreibbar.');
            }
        }

        @set_time_limit(600);
        $work = $this->base.'/storage/app/updates/'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($work);

        $backup = null;
        $migrated = false;
        $down = false;

        try {
            $this->write('Lade Paket herunter …', $version);
            $zip = $this->download($release['asset_url'], $release['sha256'], $work);

            $this->write('Entpacke Paket …', $version);
            $new = $this->extract($zip, $work.'/new', $version);

            $this->write('Erstelle Sicherung …', $version);
            $backup = $this->backup($version);

            $this->write('Wartungsmodus …', $version);
            $this->artisan('down', ['--retry' => 60]);
            $down = true;

            $this->write('Installiere Dateien …', $version);
            foreach (self::MANAGED as $path) {
                $this->mirror($new.'/'.$path, $this->base.'/'.$path);
            }

            $this->write('Aktualisiere Datenbank …', $version);
            $migrated = true;
            $this->artisan('migrate', ['--force' => true]);
            $this->artisan('optimize:clear');

            $this->artisan('up');
            $down = false;
            Cache::forget('tickets.update.latest');
            $this->write('Erfolgreich auf Version '.$version.' aktualisiert.', $version, 'ok');
        } catch (\Throwable $e) {
            Log::error('Update fehlgeschlagen', ['version' => $version, 'error' => $e->getMessage()]);
            $this->write('Fehlgeschlagen: '.$e->getMessage().' – Rollback läuft …', $version, 'running');

            try {
                if ($migrated) {
                    // Zuerst Migrationen zurück (die neuen Migrationsdateien sind noch vorhanden), dann Dateien.
                    $this->artisan('migrate:rollback', ['--force' => true, '--step' => 1]);
                }
                if ($backup) {
                    $this->restore($backup);
                    $this->artisan('optimize:clear');
                }
                $this->write('Fehlgeschlagen und zurückgerollt: '.$e->getMessage(), $version, 'failed');
            } catch (\Throwable $rollback) {
                Log::critical('Rollback fehlgeschlagen', ['error' => $rollback->getMessage()]);
                $this->write('Fehlgeschlagen, Rollback ebenfalls: '.$rollback->getMessage().' Sicherung: '.$backup, $version, 'failed');
            }

            if ($down) {
                $this->artisan('up');
            }

            throw new RuntimeException($e->getMessage(), 0, $e);
        } finally {
            File::deleteDirectory($work);
            $this->pruneBackups();
        }
    }

    // ---------------------------------------------------------------------------------------------

    private function withNewer(array $release): array
    {
        $release['newer'] = version_compare($release['version'], $this->currentVersion(), '>');

        return $release;
    }

    /** Nur HTTPS zu GitHub; Dateien müssen aus dem eigenen Repository stammen. */
    private function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        if (($parts['scheme'] ?? '') !== 'https' || ! in_array($host, self::ALLOWED_HOSTS, true)) {
            throw new RuntimeException('Die Download-Adresse ist nicht zulässig.');
        }
        if (in_array($host, ['github.com', 'api.github.com'], true) && ! str_contains(strtolower($parts['path'] ?? ''), strtolower(self::REPOSITORY).'/')) {
            throw new RuntimeException('Das Paket stammt nicht aus dem offiziellen Repository.');
        }
    }

    private function download(string $url, string $sha256, string $work): string
    {
        $this->assertAllowedUrl($url);

        $target = $work.'/release.zip';
        $response = Http::withHeaders(['User-Agent' => 'vylaris-tickets-updater'])->timeout(300)->sink($target)->get($url);

        if (! $response->ok() || ! is_file($target)) {
            throw new RuntimeException('Der Download ist fehlgeschlagen (HTTP '.$response->status().').');
        }
        if (! hash_equals(strtolower($sha256), hash_file('sha256', $target))) {
            throw new RuntimeException('Die Prüfsumme des Pakets stimmt nicht überein.');
        }

        return $target;
    }

    /** Entpackt mit Schutz vor Pfad-Tricks (Zip-Slip) und prüft Inhalt und Version. */
    private function extract(string $zipPath, string $to, string $version): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Das Paket ist beschädigt.');
        }

        File::ensureDirectoryExists($to);
        $root = realpath($to);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));

            if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('/^[A-Za-z]:/', $name)) {
                $zip->close();
                throw new RuntimeException('Das Paket enthält unzulässige Pfade.');
            }
        }

        if (! $zip->extractTo($root)) {
            $zip->close();
            throw new RuntimeException('Das Paket konnte nicht entpackt werden.');
        }
        $zip->close();

        // Paket kann einen einzelnen Wurzelordner enthalten.
        $entries = array_values(array_diff(scandir($root), ['.', '..']));
        $content = (count($entries) === 1 && is_dir($root.'/'.$entries[0]) && ! is_file($root.'/artisan')) ? $root.'/'.$entries[0] : $root;

        foreach (['artisan', 'VERSION', 'vendor/autoload.php', 'public/build', 'app'] as $required) {
            if (! file_exists($content.'/'.$required)) {
                throw new RuntimeException("Das Paket ist unvollständig ($required fehlt).");
            }
        }
        if (trim((string) file_get_contents($content.'/VERSION')) !== $version) {
            throw new RuntimeException('Die Version im Paket passt nicht zum Release.');
        }

        return $content;
    }

    private function backup(string $version): string
    {
        $dir = $this->base.'/storage/app/backups';
        File::ensureDirectoryExists($dir);
        $file = $dir.'/'.str_replace('/', '', $this->currentVersion()).'-vor-'.$version.'-'.date('Ymd-His').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Die Sicherung konnte nicht erstellt werden.');
        }

        foreach (self::MANAGED as $path) {
            $abs = $this->base.'/'.$path;
            if (is_file($abs)) {
                $zip->addFile($abs, $path);
            } elseif (is_dir($abs)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($abs, \FilesystemIterator::SKIP_DOTS)) as $f) {
                    $rel = substr($f->getPathname(), strlen($this->base) + 1);
                    if ($f->isFile() && ! $this->preserved($rel)) {
                        $zip->addFile($f->getPathname(), $rel);
                    }
                }
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException('Die Sicherung konnte nicht geschrieben werden.');
        }

        return $file;
    }

    private function restore(string $backup): void
    {
        $tmp = dirname($backup).'/restore-'.bin2hex(random_bytes(4));
        $zip = new ZipArchive;

        if ($zip->open($backup) !== true || ! $zip->extractTo($tmp)) {
            throw new RuntimeException('Die Sicherung konnte nicht gelesen werden.');
        }
        $zip->close();

        foreach (self::MANAGED as $path) {
            $this->mirror($tmp.'/'.$path, $this->base.'/'.$path);
        }

        File::deleteDirectory($tmp);
    }

    /** Spiegelt Quelle nach Ziel (kopiert Neues, entfernt Veraltetes). Fehlt die Quelle, wird das Ziel entfernt. */
    private function mirror(string $src, string $dst): void
    {
        if (is_file($src)) {
            File::ensureDirectoryExists(dirname($dst));
            copy($src, $dst);

            return;
        }

        if (! is_dir($src)) {
            // In der Quelle nicht vorhanden: veraltetes Ziel entfernen, aber nur innerhalb verwalteter Pfade.
            if (is_dir($dst)) {
                $this->deleteTree($dst, $this->relative($dst));
            } elseif (is_file($dst)) {
                @unlink($dst);
            }

            return;
        }

        File::ensureDirectoryExists($dst);
        $keep = [];

        foreach (new \DirectoryIterator($src) as $item) {
            if ($item->isDot()) {
                continue;
            }
            $keep[$item->getFilename()] = true;
            $rel = $this->relative($dst.'/'.$item->getFilename());

            if ($this->preserved($rel)) {
                continue;
            }

            $this->mirror($item->getPathname(), $dst.'/'.$item->getFilename());
        }

        foreach (new \DirectoryIterator($dst) as $item) {
            if ($item->isDot() || isset($keep[$item->getFilename()]) || $this->preserved($this->relative($item->getPathname()))) {
                continue;
            }
            $item->isDir() && ! $item->isLink() ? $this->deleteTree($item->getPathname(), $this->relative($item->getPathname())) : @unlink($item->getPathname());
        }
    }

    private function deleteTree(string $dir, string $rel): void
    {
        if ($this->preserved($rel)) {
            return;
        }
        File::deleteDirectory($dir);
    }

    private function relative(string $abs): string
    {
        return ltrim(substr($abs, strlen($this->base)), '/');
    }

    private function preserved(string $rel): bool
    {
        foreach (self::PRESERVE as $p) {
            if ($rel === $p || str_starts_with($rel, $p.'/')) {
                return true;
            }
        }

        return false;
    }

    private function pruneBackups(): void
    {
        $files = glob($this->base.'/storage/app/backups/*.zip') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        foreach (array_slice($files, 3) as $old) {
            @unlink($old);
        }
    }

    private function write(string $message, string $version, string $state = 'running'): void
    {
        $file = $this->base.'/storage/app/update-status.json';
        File::ensureDirectoryExists(dirname($file));
        file_put_contents($file, json_encode(['state' => $state, 'message' => $message, 'version' => $version, 'at' => date('c')], JSON_UNESCAPED_UNICODE));
    }

    protected function artisan(string $command, array $options = []): void
    {
        $code = Artisan::call($command, $options);

        if ($code !== 0) {
            throw new RuntimeException("«php artisan $command» ist fehlgeschlagen: ".trim(Artisan::output()));
        }
    }
}
