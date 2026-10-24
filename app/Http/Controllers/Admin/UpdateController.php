<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Updater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    public function index(Request $request, Updater $updater)
    {
        $latest = null;
        $error = null;

        try {
            $latest = $updater->latest($request->boolean('refresh'));
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('admin.update', [
            'current' => $updater->currentVersion(), 'latest' => $latest, 'error' => $error, 'status' => $updater->status(), 'deps' => $updater->dependencyInfo(),
        ]);
    }

    public function install(Request $request, Updater $updater): RedirectResponse
    {
        $request->validate(['version' => ['required', 'regex:/^\d+\.\d+\.\d+$/']]);

        try {
            $latest = $updater->latest();
            abort_unless($latest['version'] === $request->input('version'), 409, 'Die Version hat sich geändert. Bitte neu prüfen.');
            $updater->install($latest);
        } catch (\Throwable $e) {
            return redirect()->route('admin.update')->withErrors(['update' => $e->getMessage()]);
        }

        return redirect()->route('admin.update')->with('status', 'Update auf Version '.$latest['version'].' installiert.');
    }
}
