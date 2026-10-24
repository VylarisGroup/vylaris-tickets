<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Organizer;
use App\Http\Controllers\Reseller;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/registrieren', [RegisterController::class, 'create'])->name('register');
    Route::post('/registrieren', [RegisterController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Plattform-Administration
    Route::prefix('admin')->name('admin.')->middleware('can:platform-admin')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::resource('resellers', Admin\ResellerController::class)->except('show');
        Route::get('/veranstalter', [Admin\OrganizerController::class, 'index'])->name('organizers.index');
    });

    // Reseller-Bereich (Plesk-Prinzip: Reseller verwalten ihre eigenen Veranstalter)
    Route::prefix('reseller/{reseller:slug}')->name('reseller.')->middleware('can:manage-reseller,reseller')->group(function () {
        Route::get('/', Reseller\DashboardController::class)->name('dashboard');
        Route::get('/veranstalter', [Reseller\OrganizerController::class, 'index'])->name('organizers.index');
        Route::get('/veranstalter/neu', [Reseller\OrganizerController::class, 'create'])->name('organizers.create');
        Route::post('/veranstalter', [Reseller\OrganizerController::class, 'store'])->name('organizers.store');
        Route::patch('/veranstalter/{organizer:slug}/sperren', [Reseller\OrganizerController::class, 'toggle'])->name('organizers.toggle');
        Route::get('/branding', [Reseller\BrandingController::class, 'edit'])->name('branding.edit');
        Route::put('/branding', [Reseller\BrandingController::class, 'update'])->name('branding.update');
    });

    // Veranstalter-Bereich
    Route::prefix('veranstalter/{organizer:slug}')->name('organizer.')->middleware('can:access-organizer,organizer')->group(function () {
        Route::get('/', Organizer\DashboardController::class)->name('dashboard');

        Route::middleware('can:manage-organizer,organizer')->group(function () {
            Route::get('/einstellungen', [Organizer\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/einstellungen', [Organizer\SettingsController::class, 'update'])->name('settings.update');
            Route::get('/team', [Organizer\MemberController::class, 'index'])->name('members.index');
            Route::post('/team', [Organizer\MemberController::class, 'store'])->name('members.store');
            Route::put('/team/{user}', [Organizer\MemberController::class, 'update'])->name('members.update');
            Route::delete('/team/{user}', [Organizer\MemberController::class, 'destroy'])->name('members.destroy');
        });
    });
});
