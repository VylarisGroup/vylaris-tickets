<?php

namespace App\Providers;

use App\Models\Organizer;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('platform-admin', fn (User $user) => $user->is_platform_admin);
        Gate::define('manage-reseller', fn (User $user, Reseller $reseller) => $user->canManageReseller($reseller));
        Gate::define('manage-organizer', fn (User $user, Organizer $organizer) => $user->canManageOrganizer($organizer));
        Gate::define('access-organizer', fn (User $user, Organizer $organizer) => $user->canAccessOrganizer($organizer));
    }
}
