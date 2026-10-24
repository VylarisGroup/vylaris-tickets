<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Updater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class UpdaterTest extends TestCase
{
    use RefreshDatabase;

    private const ZIPBALL = 'https://api.github.com/repos/VylarisGroup/vylaris-tickets/zipball/v9.9.0';

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->base = sys_get_temp_dir().'/vt-update-'.bin2hex(random_bytes(4));
        foreach (['app', 'vendor', 'config', 'storage/app', 'public/build', 'bootstrap/cache'] as $dir) {
            File::ensureDirectoryExists($this->base.'/'.$dir);
        }
        file_put_contents($this->base.'/VERSION', "1.0.0\n");
        file_put_contents($this->base.'/artisan', 'old');
        file_put_contents($this->base.'/composer.lock', 'lock-v1');
        file_put_contents($this->base.'/app/Old.php', 'old');
        file_put_contents($this->base.'/app/Obsolete.php', 'obsolete');
        file_put_contents($this->base.'/vendor/autoload.php', 'old-vendor');
        file_put_contents($this->base.'/.env', 'APP_KEY=secret');
        file_put_contents($this->base.'/storage/app/keep.txt', 'user data');
        file_put_contents($this->base.'/bootstrap/cache/services.php', 'cache');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    /** Baut eine ZIP wie GitHub sie erzeugt: ein Wurzelordner, Quellcode inkl. public/build, ohne vendor. */
    private function package(array $extra = [], string $root = 'VylarisGroup-vylaris-tickets-abc1234/'): string
    {
        $path = sys_get_temp_dir().'/vt-pkg-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $files = array_merge([
            'VERSION' => "0.0.1-quelle\n", 'artisan' => 'new', 'composer.json' => '{}', 'composer.lock' => 'lock-v1',
            'app/New.php' => 'new', 'public/build/manifest.json' => '{}', 'config/x.php' => '<?php return [];', 'bootstrap/app.php' => 'new',
        ], $extra);
        foreach ($files as $name => $content) {
            if ($content !== null) {
                $zip->addFromString($root.$name, $content);
            }
        }
        $zip->close();

        return $path;
    }

    private function fakeRelease(string $zipPath, string $tag = 'v9.9.0', array $override = []): void
    {
        Http::fake([
            'api.github.com/repos/VylarisGroup/vylaris-tickets/releases/latest' => Http::response(array_merge([
                'tag_name' => $tag, 'body' => 'Neu: Dinge', 'html_url' => 'https://github.com/VylarisGroup/vylaris-tickets/releases/tag/'.$tag,
                'zipball_url' => 'https://api.github.com/repos/VylarisGroup/vylaris-tickets/zipball/'.$tag,
            ], $override)),
            'api.github.com/repos/VylarisGroup/vylaris-tickets/zipball/*' => Http::response(file_get_contents($zipPath)),
        ]);
    }

    private function updater(?callable $composer = null): Updater
    {
        return new class($this->base, $composer) extends Updater
        {
            public array $calls = [];

            public ?string $failOn = null;

            public int $composerRuns = 0;

            public function __construct(string $base, private $composer)
            {
                parent::__construct($base);
            }

            protected function artisan(string $command, array $options = []): void
            {
                $this->calls[] = $command;
                if ($command === $this->failOn) {
                    throw new RuntimeException('Migration kaputt');
                }
            }

            protected function runComposer(string $dir): void
            {
                $this->composerRuns++;
                if ($this->composer) {
                    ($this->composer)($dir);
                } else {
                    throw new RuntimeException('Composer ist auf dem Server nicht verfügbar. Es wurde nichts verändert.');
                }
            }
        };
    }

    public function test_latest_detects_newer_version_from_the_release_tag(): void
    {
        $this->fakeRelease($this->package());

        $latest = $this->updater()->latest(true);

        $this->assertSame('9.9.0', $latest['version']);
        $this->assertTrue($latest['newer']);
        $this->assertSame(self::ZIPBALL, $latest['zip_url']);
        $this->assertSame('Neu: Dinge', $latest['notes']);
    }

    public function test_same_version_is_not_newer(): void
    {
        file_put_contents($this->base.'/VERSION', '9.9.0');
        $this->fakeRelease($this->package());

        $this->assertFalse($this->updater()->latest(true)['newer']);
    }

    public function test_successful_install_swaps_code_keeps_vendor_and_data_and_creates_backup(): void
    {
        $this->fakeRelease($this->package());
        $updater = $this->updater();

        $updater->install($updater->latest(true));

        $this->assertSame('9.9.0', $updater->currentVersion(), 'Version kommt aus dem Tag, nicht aus der Datei im Paket');
        $this->assertSame('new', file_get_contents($this->base.'/artisan'));
        $this->assertFileExists($this->base.'/app/New.php');
        $this->assertFileDoesNotExist($this->base.'/app/Old.php', 'Veraltete Dateien werden entfernt');
        $this->assertFileExists($this->base.'/public/build/manifest.json');
        $this->assertSame('old-vendor', file_get_contents($this->base.'/vendor/autoload.php'), 'composer.lock unverändert: vendor bleibt');
        $this->assertSame(0, $updater->composerRuns);
        $this->assertSame('APP_KEY=secret', file_get_contents($this->base.'/.env'));
        $this->assertSame('user data', file_get_contents($this->base.'/storage/app/keep.txt'));
        $this->assertSame('cache', file_get_contents($this->base.'/bootstrap/cache/services.php'));
        $this->assertCount(1, glob($this->base.'/storage/app/backups/*.zip'));
        $this->assertSame(['down', 'package:discover', 'migrate', 'optimize:clear', 'up'], $updater->calls);
        $this->assertSame('ok', $updater->status()['state']);
    }

    public function test_changed_composer_lock_installs_dependencies_before_touching_anything(): void
    {
        $this->fakeRelease($this->package(['composer.lock' => 'lock-v2']));
        $updater = $this->updater(function (string $dir) {
            File::ensureDirectoryExists($dir.'/vendor');
            file_put_contents($dir.'/vendor/autoload.php', 'new-vendor');
        });

        $updater->install($updater->latest(true));

        $this->assertSame(1, $updater->composerRuns);
        $this->assertSame('new-vendor', file_get_contents($this->base.'/vendor/autoload.php'));
        $this->assertSame('lock-v2', file_get_contents($this->base.'/composer.lock'));
    }

    public function test_missing_composer_aborts_without_changes(): void
    {
        $this->fakeRelease($this->package(['composer.lock' => 'lock-v2']));
        $updater = $this->updater();

        try {
            $updater->install($updater->latest(true));
            $this->fail('Erwartet: Exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Composer', $e->getMessage());
        }

        $this->assertSame('1.0.0', $updater->currentVersion());
        $this->assertSame('old', file_get_contents($this->base.'/artisan'));
        $this->assertSame('old-vendor', file_get_contents($this->base.'/vendor/autoload.php'));
        $this->assertNotContains('down', $updater->calls, 'Wartungsmodus wurde gar nicht erst aktiviert');
    }

    public function test_failed_migration_rolls_back_files_and_database(): void
    {
        $this->fakeRelease($this->package());
        $updater = $this->updater();
        $updater->failOn = 'migrate';

        try {
            $updater->install($updater->latest(true));
            $this->fail('Erwartet: Exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Migration kaputt', $e->getMessage());
        }

        $this->assertSame('1.0.0', $updater->currentVersion());
        $this->assertSame('old', file_get_contents($this->base.'/artisan'));
        $this->assertFileExists($this->base.'/app/Old.php');
        $this->assertFileExists($this->base.'/app/Obsolete.php');
        $this->assertFileDoesNotExist($this->base.'/app/New.php');
        $this->assertSame('old-vendor', file_get_contents($this->base.'/vendor/autoload.php'), 'vendor wurde nicht angefasst und bleibt erhalten');
        $this->assertContains('migrate:rollback', $updater->calls);
        $this->assertSame('up', end($updater->calls), 'Wartungsmodus wird wieder beendet');
        $this->assertSame('failed', $updater->status()['state']);
    }

    public function test_package_without_built_assets_is_rejected(): void
    {
        $this->fakeRelease($this->package(['public/build/manifest.json' => null]));
        $updater = $this->updater();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unvollständig');
        $updater->install($updater->latest(true));
    }

    public function test_zip_slip_paths_are_rejected(): void
    {
        $this->fakeRelease($this->package(['../evil.php' => 'x']));
        $updater = $this->updater();

        try {
            $updater->install($updater->latest(true));
            $this->fail('Erwartet: Exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('unzulässige Pfade', $e->getMessage());
        }

        $this->assertFileDoesNotExist(dirname($this->base).'/evil.php');
        $this->assertSame('old', file_get_contents($this->base.'/artisan'));
    }

    public function test_downloads_from_foreign_hosts_are_refused(): void
    {
        $this->fakeRelease($this->package(), 'v9.9.0', ['zipball_url' => 'https://evil.example.com/repos/VylarisGroup/vylaris-tickets/zipball/v9.9.0']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nicht zulässig');
        $this->updater()->latest(true);
    }

    public function test_plain_http_is_refused(): void
    {
        $this->fakeRelease($this->package(), 'v9.9.0', ['zipball_url' => 'http://api.github.com/repos/VylarisGroup/vylaris-tickets/zipball/v9.9.0']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nicht zulässig');
        $this->updater()->latest(true);
    }

    public function test_other_github_repositories_are_refused(): void
    {
        $this->fakeRelease($this->package(), 'v9.9.0', ['zipball_url' => 'https://api.github.com/repos/someone/else/zipball/v9.9.0']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('offiziellen Repository');
        $this->updater()->latest(true);
    }

    public function test_invalid_tag_format_is_rejected(): void
    {
        $this->fakeRelease($this->package(), 'nightly');

        $this->expectException(RuntimeException::class);
        $this->updater()->latest(true);
    }

    public function test_zipball_is_downloaded_from_the_github_api(): void
    {
        $this->fakeRelease($this->package());
        $updater = $this->updater();

        $updater->install($updater->latest(true));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/zipball/') && $r->hasHeader('User-Agent'));
    }

    public function test_only_platform_admins_reach_the_update_page(): void
    {
        $this->fakeRelease($this->package());

        $this->actingAs(User::factory()->create())->get(route('admin.update'))->assertForbidden();
        $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->get(route('admin.update'))->assertOk()->assertSee('Installierte Version')->assertSee('9.9.0');
    }

    private function vendorZip(string $lock, array $extra = []): string
    {
        $path = sys_get_temp_dir().'/vt-vendor-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('vendor/autoload.php', 'zip-vendor');
        $zip->addFromString('lock.md5', md5($lock));
        foreach ($extra as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $path;
    }

    private function fakeReleaseWithVendor(string $zipPath, string $vendorPath): void
    {
        $url = 'https://github.com/VylarisGroup/vylaris-tickets/releases/download/v9.9.0/vendor.zip';
        $this->fakeRelease($zipPath, 'v9.9.0', ['assets' => [['name' => 'vendor.zip', 'browser_download_url' => $url]]]);
        Http::fake([$url => Http::response(file_get_contents($vendorPath))]);
    }

    public function test_without_composer_the_prebuilt_vendor_zip_is_used(): void
    {
        $this->fakeReleaseWithVendor($this->package(['composer.lock' => 'lock-v2']), $this->vendorZip('lock-v2'));
        $updater = $this->updater();

        $updater->install($updater->latest(true));

        $this->assertSame('zip-vendor', file_get_contents($this->base.'/vendor/autoload.php'));
        $this->assertSame('lock-v2', file_get_contents($this->base.'/composer.lock'));
        $this->assertSame('9.9.0', $updater->currentVersion());
    }

    public function test_vendor_zip_for_another_lock_file_is_rejected_without_changes(): void
    {
        $this->fakeReleaseWithVendor($this->package(['composer.lock' => 'lock-v2']), $this->vendorZip('lock-v3'));
        $updater = $this->updater();

        try {
            $updater->install($updater->latest(true));
            $this->fail('Erwartet: Exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('passt nicht zur composer.lock', $e->getMessage());
            $this->assertStringContainsString('tickets:update', $e->getMessage());
        }

        $this->assertSame('1.0.0', $updater->currentVersion());
        $this->assertSame('old-vendor', file_get_contents($this->base.'/vendor/autoload.php'));
    }

    public function test_vendor_zip_with_foreign_paths_is_rejected(): void
    {
        $this->fakeReleaseWithVendor($this->package(['composer.lock' => 'lock-v2']), $this->vendorZip('lock-v2', ['app/Evil.php' => 'x']));
        $updater = $this->updater();

        $this->expectExceptionMessage('unzulässige Pfade');
        $updater->install($updater->latest(true));
    }

    public function test_console_command_reports_and_installs(): void
    {
        $this->fakeRelease($this->package());
        $this->artisan('tickets:update', ['--check' => true])->assertSuccessful();
    }
}
