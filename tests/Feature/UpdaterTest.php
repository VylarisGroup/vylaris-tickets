<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Updater;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class UpdaterTest extends TestCase
{
    use RefreshDatabase;

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
        file_put_contents($this->base.'/app/Old.php', 'old');
        file_put_contents($this->base.'/app/Obsolete.php', 'obsolete');
        file_put_contents($this->base.'/vendor/autoload.php', 'old');
        file_put_contents($this->base.'/.env', 'APP_KEY=secret');
        file_put_contents($this->base.'/storage/app/keep.txt', 'user data');
        file_put_contents($this->base.'/bootstrap/cache/services.php', 'cache');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    private function package(string $version, array $extra = [], bool $wrap = false): string
    {
        $path = sys_get_temp_dir().'/vt-pkg-'.bin2hex(random_bytes(4)).'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $prefix = $wrap ? 'vylaris-tickets/' : '';
        $files = array_merge([
            'VERSION' => $version, 'artisan' => 'new', 'app/New.php' => 'new', 'vendor/autoload.php' => 'new',
            'public/build/manifest.json' => '{}', 'config/x.php' => '<?php return [];', 'bootstrap/app.php' => 'new',
        ], $extra);
        foreach ($files as $name => $content) {
            $zip->addFromString($prefix.$name, $content);
        }
        $zip->close();

        return $path;
    }

    private function fakeRelease(string $zipPath, string $tag = 'v1.1.0', ?string $sha = null): void
    {
        $sha ??= hash_file('sha256', $zipPath);
        $name = 'vylaris-tickets-'.ltrim($tag, 'v').'.zip';
        $url = 'https://github.com/VylarisGroup/vylaris-tickets/releases/download/'.$tag.'/'.$name;

        Http::fake([
            'api.github.com/repos/VylarisGroup/vylaris-tickets/releases/latest' => Http::response([
                'tag_name' => $tag, 'body' => 'Neu: Dinge', 'html_url' => 'https://github.com/VylarisGroup/vylaris-tickets/releases/tag/'.$tag,
                'assets' => [['name' => $name, 'browser_download_url' => $url, 'digest' => 'sha256:'.$sha]],
            ]),
            $url => Http::response(file_get_contents($zipPath)),
        ]);
    }

    private function updater(): Updater
    {
        return new class($this->base) extends Updater
        {
            public array $calls = [];

            public ?string $failOn = null;

            protected function artisan(string $command, array $options = []): void
            {
                $this->calls[] = $command;
                if ($command === $this->failOn) {
                    throw new RuntimeException('Migration kaputt');
                }
            }
        };
    }

    public function test_latest_detects_newer_version_and_asset(): void
    {
        $this->fakeRelease($this->package('1.1.0'));

        $latest = $this->updater()->latest(true);

        $this->assertSame('1.1.0', $latest['version']);
        $this->assertTrue($latest['newer']);
        $this->assertNotEmpty($latest['sha256']);
        $this->assertSame('Neu: Dinge', $latest['notes']);
    }

    public function test_same_version_is_not_newer(): void
    {
        file_put_contents($this->base.'/VERSION', '1.1.0');
        $this->fakeRelease($this->package('1.1.0'));

        $this->assertFalse($this->updater()->latest(true)['newer']);
    }

    public function test_successful_install_swaps_code_keeps_data_and_creates_backup(): void
    {
        $this->fakeRelease($this->package('1.1.0', [], true));
        $updater = $this->updater();

        $updater->install($updater->latest(true));

        $this->assertSame('1.1.0', $updater->currentVersion());
        $this->assertSame('new', file_get_contents($this->base.'/artisan'));
        $this->assertFileExists($this->base.'/app/New.php');
        $this->assertFileDoesNotExist($this->base.'/app/Old.php', 'Veraltete Dateien werden entfernt');
        $this->assertFileDoesNotExist($this->base.'/app/Obsolete.php');
        $this->assertSame('APP_KEY=secret', file_get_contents($this->base.'/.env'));
        $this->assertSame('user data', file_get_contents($this->base.'/storage/app/keep.txt'));
        $this->assertSame('cache', file_get_contents($this->base.'/bootstrap/cache/services.php'));
        $this->assertCount(1, glob($this->base.'/storage/app/backups/*.zip'));
        $this->assertSame(['down', 'migrate', 'optimize:clear', 'up'], $updater->calls);
        $this->assertSame('ok', $updater->status()['state']);
    }

    public function test_failed_migration_rolls_back_files_and_database(): void
    {
        $this->fakeRelease($this->package('1.1.0'));
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
        $this->assertContains('migrate:rollback', $updater->calls);
        $this->assertSame('up', end($updater->calls), 'Wartungsmodus wird wieder beendet');
        $this->assertSame('failed', $updater->status()['state']);
    }

    public function test_wrong_checksum_is_rejected_before_anything_changes(): void
    {
        $zip = $this->package('1.1.0');
        $this->fakeRelease($zip, 'v1.1.0', str_repeat('a', 64));
        $updater = $this->updater();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prüfsumme');

        try {
            $updater->install($updater->latest(true));
        } finally {
            $this->assertSame('old', file_get_contents($this->base.'/artisan'));
            $this->assertSame([], $updater->calls);
        }
    }

    public function test_version_mismatch_inside_package_is_rejected(): void
    {
        $this->fakeRelease($this->package('9.9.9'));
        $updater = $this->updater();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Version im Paket');
        $updater->install($updater->latest(true));
    }

    public function test_zip_slip_paths_are_rejected(): void
    {
        $this->fakeRelease($this->package('1.1.0', ['../evil.php' => 'x']));
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
        $zip = $this->package('1.1.0');
        Http::fake(['api.github.com/*' => Http::response([
            'tag_name' => 'v1.1.0',
            'assets' => [['name' => 'vylaris-tickets-1.1.0.zip', 'browser_download_url' => 'https://evil.example.com/vylaris-tickets-1.1.0.zip', 'digest' => 'sha256:'.hash_file('sha256', $zip)]],
        ])]);
        $updater = $this->updater();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('nicht zulässig');
        $updater->install($updater->latest(true));
    }

    public function test_downloads_from_other_github_repositories_are_refused(): void
    {
        $zip = $this->package('1.1.0');
        Http::fake(['api.github.com/*' => Http::response([
            'tag_name' => 'v1.1.0',
            'assets' => [['name' => 'vylaris-tickets-1.1.0.zip', 'browser_download_url' => 'https://github.com/someone/else/releases/download/v1.1.0/vylaris-tickets-1.1.0.zip', 'digest' => 'sha256:'.hash_file('sha256', $zip)]],
        ])]);
        $updater = $this->updater();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('offiziellen Repository');
        $updater->install($updater->latest(true));
    }

    public function test_release_without_checksum_is_not_installed(): void
    {
        Http::fake(['api.github.com/*' => Http::response([
            'tag_name' => 'v1.1.0',
            'assets' => [['name' => 'vylaris-tickets-1.1.0.zip', 'browser_download_url' => 'https://github.com/VylarisGroup/vylaris-tickets/releases/download/v1.1.0/vylaris-tickets-1.1.0.zip']],
        ])]);
        $updater = $this->updater();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prüfsumme');
        $updater->install($updater->latest(true));
    }

    public function test_only_platform_admins_reach_the_update_page(): void
    {
        $this->fakeRelease($this->package('1.1.0'));

        $this->actingAs(User::factory()->create())->get(route('admin.update'))->assertForbidden();
        $this->actingAs(User::factory()->create(['is_platform_admin' => true]))->get(route('admin.update'))->assertOk()->assertSee('Installierte Version');
    }
}
