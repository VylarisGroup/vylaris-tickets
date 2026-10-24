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
 * Update-System: lädt die von GitHub automatisch erzeugte ZIP-Datei des neuesten Releases aus dem öffentlichen
 * Repository von Vylaris Tickets, sichert die aktuelle Installation, aktualisiert im Wartungsmodus und rollt
 * bei Fehlern zurück.
 *
 * Vertrauensgrundlage ist ausschliesslich HTTPS zu GitHub (keine Prüfsumme). Die Quelle ist bewusst fest im
 * Code verankert und nicht konfigurierbar; Weiterleitungen sind auf HTTPS beschränkt.
 *
 * Die ZIP-Datei enthält den Quellcode inklusive der eingecheckten Assets (public/build), aber kein `vendor`.
 * Bleibt composer.lock unverändert, wird das vorhandene `vendor` weiterverwendet; ändert es sich, wird
 * `composer install` im entpackten Paket ausgeführt, bevor irgendetwas an der Installation verändert wird.
 */
class Updater
{
    public const REPOSITORY = 'VylarisGroup/vylaris-tickets';

    private const ALLOWED_HOSTS = ['api.github.com', 'github.com', 'codeload.github.com'];

    /** Verzeichnisse und Dateien, die ein Update ersetzt. Alles andere (.env, storage, uploads) bleibt unberührt. */
    private const MANAGED = ['app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'public/build', 'public/index.php', 'public/.htaccess', 'artisan', 'composer.json', 'composer.lock', 'package.json', 'VERSION'];

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
     * @return array{version:string,tag:string,notes:string,url:string,zip_url:string,newer:bool}
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

            $zip = (string) ($release['zipball_url'] ?? '');
            $this->assertAllowedUrl($zip);

            // Optional: fertig gebaute PHP-Abhängigkeiten (vendor.zip) als Release-Datei, für Server ohne Composer.
            $vendor = '';
            foreach ((array) ($release['assets'] ?? []) as $asset) {
                if (($asset['name'] ?? '') === 'vendor.zip' && ! empty($asset['browser_download_url'])) {
                    $this->assertAllowedUrl((string) $asset['browser_download_url']);
                    $vendor = (string) $asset['browser_download_url'];
                }
            }

            return [
                'version' => $version,
                'tag' => (string) $release['tag_name'],
                'notes' => (string) ($release['body'] ?? ''),
                'url' => (string) ($release['html_url'] ?? ''),
                'zip_url' => $zip,
                'vendor_url' => $vendor,
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
        if (empty($release['zip_url'])) {
            throw new RuntimeException('Das Release enthält kein Paket zum Herunterladen.');
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
        $paths = self::MANAGED;
        $migrated = false;
        $down = false;

        try {
            $this->write('Lade Paket herunter …', $version);
            $zip = $this->download($release['zip_url'], $work);

            $this->write('Entpacke Paket …', $version);
            $new = $this->extract($zip, $work.'/new');

            // PHP-Abhängigkeiten: nur bei geänderter composer.lock neu installieren (noch bevor etwas verändert wird).
            if ($this->lockChanged($new)) {
                $this->write('Installiere PHP-Abhängigkeiten …', $version);
                $this->installDependencies($new, (string) ($release['vendor_url'] ?? ''), $work);
                $paths[] = 'vendor';
            }

            $this->write('Erstelle Sicherung …', $version);
            $backup = $this->backup($version, $paths);

            $this->write('Wartungsmodus …', $version);
            $this->artisan('down', ['--retry' => 60]);
            $down = true;

            $this->write('Installiere Dateien …', $version);
            foreach ($paths as $path) {
                $this->mirror($new.'/'.$path, $this->base.'/'.$path);
            }
            // Die Version stammt aus dem Release-Tag, nicht aus der Datei im Paket.
            file_put_contents($this->base.'/VERSION', $version."\n");

            $this->write('Aktualisiere Datenbank …', $version);
            $migrated = true;
            $this->artisan('package:discover');
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
                    $this->restore($backup, $paths);
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

    private function download(string $url, string $work): string
    {
        $this->assertAllowedUrl($url);

        $target = $work.'/release.zip';
        // Weiterleitungen (api.github.com -> codeload.github.com) nur über HTTPS folgen.
        $response = Http::withHeaders(['User-Agent' => 'vylaris-tickets-updater'])->timeout(300)
            ->withOptions(['allow_redirects' => ['max' => 5, 'protocols' => ['https']], 'sink' => $target])->get($url);

        if (! $response->ok() || ! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('Der Download ist fehlgeschlagen (HTTP '.$response->status().').');
        }

        return $target;
    }

    /** Entpackt mit Schutz vor Pfad-Tricks (Zip-Slip) und prüft den Inhalt. */
    private function extract(string $zipPath, string $to): string
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

        // GitHub packt alles in einen Ordner «Besitzer-Repo-Commit/».
        $entries = array_values(array_diff(scandir($root), ['.', '..']));
        $content = (count($entries) === 1 && is_dir($root.'/'.$entries[0]) && ! is_file($root.'/artisan')) ? $root.'/'.$entries[0] : $root;

        foreach (['artisan', 'app', 'composer.json', 'public/build/manifest.json'] as $required) {
            if (! file_exists($content.'/'.$required)) {
                throw new RuntimeException("Das Paket ist unvollständig ($required fehlt).");
            }
        }

        return $content;
    }

    private function lockChanged(string $new): bool
    {
        $old = $this->base.'/composer.lock';

        if (! is_file($new.'/composer.lock') || ! is_file($old) || ! is_file($this->base.'/vendor/autoload.php')) {
            return true;
        }

        return md5_file($new.'/composer.lock') !== md5_file($old);
    }

    /**
     * Beschafft die PHP-Abhängigkeiten für das entpackte Paket: zuerst per Composer, sonst über das fertige
     * vendor.zip des Releases. Verändert die laufende Installation nicht.
     */
    private function installDependencies(string $new, string $vendorUrl, string $work): void
    {
        $problems = [];

        try {
            $this->runComposer($new);
            if (is_file($new.'/vendor/autoload.php')) {
                return;
            }
            $problems[] = 'Composer hat keine Abhängigkeiten erzeugt.';
        } catch (\Throwable $e) {
            $problems[] = $e->getMessage();
        }

        if ($vendorUrl !== '') {
            try {
                File::deleteDirectory($new.'/vendor');
                $this->fetchVendor($new, $vendorUrl, $work);

                return;
            } catch (\Throwable $e) {
                $problems[] = 'Fertiges Paket (vendor.zip): '.$e->getMessage();
            }
        } else {
            $problems[] = 'Zu diesem Release gibt es kein fertiges vendor.zip.';
        }

        throw new RuntimeException(implode(' ', $problems).' Es wurde nichts verändert. Tipp: Auf dem Server «php artisan tickets:update» ausführen.');
    }

    /** Lädt das fertig gebaute vendor.zip und prüft, dass es zur composer.lock des Pakets passt. */
    protected function fetchVendor(string $new, string $url, string $work): void
    {
        $this->assertAllowedUrl($url);

        $target = $work.'/vendor.zip';
        $response = Http::withHeaders(['User-Agent' => 'vylaris-tickets-updater'])->timeout(300)
            ->withOptions(['allow_redirects' => ['max' => 5, 'protocols' => ['https']], 'sink' => $target])->get($url);

        if (! $response->ok() || ! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('Download fehlgeschlagen (HTTP '.$response->status().').');
        }

        $zip = new ZipArchive;
        if ($zip->open($target) !== true) {
            throw new RuntimeException('Die Datei ist beschädigt.');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            if ($name === '' || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name) || preg_match('/^[A-Za-z]:/', $name)
                || ($name !== 'lock.md5' && ! str_starts_with($name, 'vendor/'))) {
                $zip->close();
                throw new RuntimeException('Die Datei enthält unzulässige Pfade.');
            }
        }

        $expected = trim((string) $zip->getFromName('lock.md5'));
        if ($expected === '' || ! is_file($new.'/composer.lock') || $expected !== md5_file($new.'/composer.lock')) {
            $zip->close();
            throw new RuntimeException('Die Datei passt nicht zur composer.lock dieses Releases.');
        }
        if (! $zip->extractTo($new)) {
            $zip->close();
            throw new RuntimeException('Die Datei konnte nicht entpackt werden.');
        }
        $zip->close();

        if (! is_file($new.'/vendor/autoload.php')) {
            throw new RuntimeException('Die Datei enthält kein vendor/autoload.php.');
        }
    }

    /**
     * Zeigt, was der Updater auf diesem Server findet (für die Update-Seite).
     *
     * @return array{composer:?string,php:?string}
     */
    public function dependencyInfo(): array
    {
        $command = $this->composerCommand();

        return ['composer' => $command ? implode(' ', $command) : null, 'php' => $this->cliPhp()];
    }

    /** Verzeichnisse, in denen Composer/PHP üblicherweise liegen, auch wenn der PATH des Webservers sie nicht enthält. */
    private function searchDirs(): array
    {
        $home = (string) (getenv('HOME') ?: '');
        $dirs = ['/usr/local/bin', '/usr/bin', '/bin', '/opt/homebrew/bin', '/opt/cpanel/composer/bin', PHP_BINDIR];

        if ($home !== '') {
            array_push($dirs, $home.'/bin', $home.'/.local/bin', $home.'/.composer/vendor/bin', $home.'/.config/composer/vendor/bin');
        }

        return $dirs;
    }

    private ?string $cliPhpCache = null;

    /** @var string[]|null|false */
    private $composerCache = false;

    /** Liegt der Pfad ausserhalb von open_basedir? Dann darf PHP ihn nicht prüfen (Plesk u. a.), ein Programmstart funktioniert trotzdem. */
    private function outsideBasedir(string $path): bool
    {
        $allowed = trim((string) ini_get('open_basedir'));
        if ($allowed === '') {
            return false;
        }

        foreach (explode(PATH_SEPARATOR, $allowed) as $dir) {
            $dir = rtrim($dir, '/');
            if ($dir !== '' && ($path === $dir || str_starts_with($path, $dir.'/'))) {
                return false;
            }
        }

        return true;
    }

    /** true/false wenn prüfbar, null wenn open_basedir die Prüfung verbietet. */
    private function checkFile(string $path, bool $executable = false): ?bool
    {
        if ($this->outsideBasedir($path)) {
            return null;
        }

        return $executable ? @is_executable($path) : @is_file($path);
    }

    /** Startet einen Befehl kurz, um zu prüfen, ob er wirklich läuft (unabhängig von open_basedir). */
    private function runs(array $command, array $args): bool
    {
        if (! function_exists('proc_open')) {
            return false;
        }

        try {
            $process = new \Symfony\Component\Process\Process([...$command, ...$args], null, ['HOME' => sys_get_temp_dir(), 'COMPOSER_HOME' => sys_get_temp_dir()], null, 30);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    /** PHP-CLI zum Starten der Composer-Phar (unter php-fpm/Apache ist PHP_BINARY nicht verwendbar). */
    private function cliPhp(): ?string
    {
        if ($this->cliPhpCache !== null) {
            return $this->cliPhpCache ?: null;
        }

        $candidates = [];
        if (PHP_SAPI === 'cli') {
            $candidates[] = PHP_BINARY;
        }
        $candidates[] = PHP_BINDIR.'/php';
        $candidates[] = '/opt/plesk/php/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'/bin/php';
        try {
            $found = (new \Symfony\Component\Process\ExecutableFinder)->find('php', null, $this->searchDirs());
            $found && $candidates[] = $found;
        } catch (\Throwable) {
        }

        foreach (array_unique($candidates) as $path) {
            if ($this->checkFile($path, true) !== false && $this->runs([$path], ['-r', 'echo PHP_VERSION;'])) {
                return $this->cliPhpCache = $path;
            }
        }

        $this->cliPhpCache = '';

        return null;
    }

    /** @return string[]|null */
    private function composerCommand(): ?array
    {
        if ($this->composerCache !== false) {
            return $this->composerCache;
        }

        $candidates = [];
        $configured = (string) config('tickets.composer', '');
        $configured !== '' && $candidates[] = $configured;
        try {
            $found = (new \Symfony\Component\Process\ExecutableFinder)->find('composer', null, $this->searchDirs());
            $found && $candidates[] = $found;
        } catch (\Throwable) {
        }
        array_push($candidates, $this->base.'/composer.phar', dirname($this->base).'/composer.phar', '/usr/lib64/plesk-9.0/composer.phar', '/usr/lib/plesk-9.0/composer.phar', '/usr/local/psa/var/modules/composer/composer.phar');

        foreach (array_unique($candidates) as $path) {
            if ($this->checkFile($path) === false && $this->checkFile($path, true) === false) {
                continue;
            }

            $command = str_ends_with($path, '.phar') ? ($this->cliPhp() ? [$this->cliPhp(), $path] : null) : [$path];
            if ($command && $this->runs($command, ['--version', '--no-interaction'])) {
                return $this->composerCache = $command;
            }
        }

        return $this->composerCache = null;
    }

    /** Installiert die PHP-Abhängigkeiten im entpackten Paket (benötigt Composer auf dem Server). */
    protected function runComposer(string $dir): void
    {
        $command = $this->composerCommand();

        if (! $command) {
            throw new RuntimeException(function_exists('proc_open')
                ? 'Composer wurde auf dem Server nicht gefunden oder lässt sich vom Webserver nicht starten (open_basedir). Pfad notfalls in .env als TICKETS_COMPOSER=/pfad/zu/composer eintragen.'
                : 'Programme starten ist auf dem Server gesperrt (proc_open ist deaktiviert), Composer kann nicht verwendet werden.');
        }

        $home = $this->base.'/storage/app/composer-home';
        File::ensureDirectoryExists($home);

        $process = new \Symfony\Component\Process\Process(
            [...$command, 'install', '--no-dev', '--no-interaction', '--no-scripts', '--prefer-dist', '--optimize-autoloader'],
            $dir, [
                'COMPOSER_HOME' => $home, 'HOME' => $home, 'COMPOSER_NO_INTERACTION' => '1', 'COMPOSER_ALLOW_SUPERUSER' => '1',
                'PATH' => implode(PATH_SEPARATOR, array_unique(array_filter([...explode(PATH_SEPARATOR, (string) getenv('PATH')), ...$this->searchDirs()]))),
            ], null, 900,
        );

        try {
            $process->run();
        } catch (\Throwable $e) {
            throw new RuntimeException('Composer konnte nicht gestartet werden: '.$e->getMessage());
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Composer ist fehlgeschlagen: '.mb_substr(trim($process->getErrorOutput() ?: $process->getOutput()), -300));
        }
    }

    /** @param string[] $paths */
    private function backup(string $version, array $paths): string
    {
        $dir = $this->base.'/storage/app/backups';
        File::ensureDirectoryExists($dir);
        $file = $dir.'/'.str_replace('/', '', $this->currentVersion()).'-vor-'.$version.'-'.date('Ymd-His').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Die Sicherung konnte nicht erstellt werden.');
        }

        foreach ($paths as $path) {
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

    /** @param string[] $paths */
    private function restore(string $backup, array $paths): void
    {
        $tmp = dirname($backup).'/restore-'.bin2hex(random_bytes(4));
        $zip = new ZipArchive;

        if ($zip->open($backup) !== true || ! $zip->extractTo($tmp)) {
            throw new RuntimeException('Die Sicherung konnte nicht gelesen werden.');
        }
        $zip->close();

        foreach ($paths as $path) {
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
