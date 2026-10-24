<?php

namespace Tests\Feature;

use App\Models\Organizer;
use App\Models\User;
use App\Services\Installer;
use App\Support\EnvFile;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/vt-install-'.bin2hex(random_bytes(4));
        File::makeDirectory($this->tmp.'/app', 0777, true);
        $this->app->useStoragePath($this->tmp);
        $this->app->useEnvironmentPath($this->tmp);
        file_put_contents($this->tmp.'/.env', "APP_NAME=Test\n");
        config(['tickets.installer_enabled' => true, 'tickets.install_token' => '', 'app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        $this->app->forgetInstance(Installer::class);
        $this->app->singleton(Installer::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmp);
        parent::tearDown();
    }

    /** Umgebungsprüfungen (MySQL-Treiber, Build-Dateien …) sind in der Testumgebung nicht aussagekräftig. */
    private function lenientInstaller(): void
    {
        $this->app->singleton(Installer::class, fn () => new class extends Installer {
            public function requirementsMet(): bool
            {
                return true;
            }
        });
    }

    private function install(): Installer
    {
        return app(Installer::class);
    }

    public function test_env_file_round_trips_tricky_values(): void
    {
        $env = new EnvFile($this->tmp.'/.env');
        $tricky = 'p@ss"w$rd#\'\\x y';
        $env->set(['DB_PASSWORD' => $tricky, 'TICKETS_BRAND_COLOR' => '#1867F8']);
        $env->set(['APP_NAME' => 'Neu']);

        $parsed = Dotenv::parse(file_get_contents($this->tmp.'/.env'));
        $this->assertSame($tricky, $parsed['DB_PASSWORD']);
        $this->assertSame('#1867F8', $parsed['TICKETS_BRAND_COLOR']);
        $this->assertSame('Neu', $parsed['APP_NAME']);
        $this->assertSame(1, substr_count(file_get_contents($this->tmp.'/.env'), 'APP_NAME='));
    }

    public function test_env_file_rejects_newlines(): void
    {
        $this->expectException(\Throwable::class);
        (new EnvFile($this->tmp.'/.env'))->set(['X' => "a\nb"]);
    }

    public function test_uninstalled_site_redirects_to_installer(): void
    {
        $this->get('/')->assertRedirect(route('install.welcome', absolute: false));
        $this->get('/installation')->assertOk()->assertSee('Willkommen');
    }

    public function test_installer_is_gone_once_installed(): void
    {
        $this->install()->markInstalled();
        $this->get('/installation')->assertNotFound();
    }

    public function test_existing_platform_admin_counts_as_installed(): void
    {
        User::factory()->create(['is_platform_admin' => true]);
        $this->assertTrue($this->install()->isInstalled());
        $this->assertFileExists($this->install()->markerPath());
    }

    public function test_token_gate(): void
    {
        config(['tickets.install_token' => 'geheim']);
        $this->get('/installation')->assertOk()->assertSee('Setup-Token');
        $this->get('/installation/datenbank')->assertRedirect();
        $this->post('/installation/token', ['token' => 'falsch'])->assertSessionHasErrors('token');
        $this->post('/installation/token', ['token' => 'geheim'])->assertSessionHas('install.token_ok');
    }

    public function test_admin_cannot_be_created_twice(): void
    {
        $this->lenientInstaller();
        $this->install()->markInProgress();
        User::factory()->create(['is_platform_admin' => true]);
        $this->post('/installation/administrator', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'abcdefghijk1', 'password_confirmation' => 'abcdefghijk1',
        ])->assertStatus(403);
    }

    public function test_finish_marks_installed_and_locks_installer(): void
    {
        $this->lenientInstaller();
        $this->install()->markInProgress();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin)->post('/installation/fertig')->assertRedirect();
        $this->assertTrue($this->install()->isInstalled());
        $this->get('/installation')->assertNotFound();
    }

    public function test_system_check_card_on_admin_dashboard(): void
    {
        config(['tickets.installer_enabled' => false]);
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Einrichtung');
    }
}
