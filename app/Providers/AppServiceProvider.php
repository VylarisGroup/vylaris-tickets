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
        \Illuminate\Support\Facades\View::composer('admin._subnav', fn ($view) => $view->with('openReports', \App\Models\Report::where('status', 'open')->count()));

        Gate::define('platform-admin', fn (User $user) => $user->is_platform_admin);
        Gate::define('manage-reseller', fn (User $user, Reseller $reseller) => $user->canManageReseller($reseller));
        Gate::define('manage-organizer', fn (User $user, Organizer $organizer) => $user->canManageOrganizer($organizer));
        Gate::define('sell-organizer', fn (User $user, Organizer $organizer) => $user->canSellAt($organizer));
        Gate::define('scan-organizer', fn (User $user, Organizer $organizer) => $user->canScanAt($organizer));
        Gate::define('access-organizer', fn (User $user, Organizer $organizer) => $user->canAccessOrganizer($organizer));
    }
}
