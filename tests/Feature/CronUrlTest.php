<?php

namespace Tests\Feature;

use App\Services\SystemCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_cron_url_runs_scheduler_with_valid_token(): void
    {
        config(['tickets.cron_token' => 'abc123']);
        Cache::forget(SystemCheck::HEARTBEAT_KEY);

        $this->get('/cron/abc123')->assertOk()->assertSee('OK');
        $this->assertNotNull(Cache::get(SystemCheck::HEARTBEAT_KEY));
    }

    public function test_wrong_token_or_missing_token_is_404(): void
    {
        config(['tickets.cron_token' => 'abc123']);
        $this->get('/cron/falsch')->assertNotFound();

        config(['tickets.cron_token' => null]);
        $this->get('/cron/')->assertNotFound();
        $this->get('/cron/x')->assertNotFound();
    }

    public function test_post_is_accepted_without_csrf(): void
    {
        config(['tickets.cron_token' => 'abc123']);
        $this->post('/cron/abc123')->assertOk();
    }
}
