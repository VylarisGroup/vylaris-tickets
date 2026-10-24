<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ResellerRole;
use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ResellerController extends Controller
{
    public function index()
    {
        return view('admin.resellers.index', [
            'resellers' => Reseller::withCount('organizers')->orderBy('name')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.resellers.form', ['reseller' => new Reseller(['allow_registration' => true, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->rules(),
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email', 'max:190'],
            'owner_password' => ['nullable', Password::min(10)],
        ]);

        DB::transaction(function () use ($data) {
            $reseller = Reseller::create($this->attributes($data));

            $owner = User::where('email', $data['owner_email'])->first();

            if (! $owner) {
                abort_if(empty($data['owner_password']), 422, 'Für einen neuen Benutzer ist ein Passwort nötig.');

                $owner = User::create([
                    'name' => $data['owner_name'],
                    'email' => $data['owner_email'],
                    'password' => $data['owner_password'],
                ]);
            }

            $reseller->users()->attach($owner->id, ['role' => ResellerRole::Owner->value]);
        });

        return redirect()->route('admin.resellers.index')->with('status', 'Reseller wurde angelegt.');
    }

    public function edit(Reseller $reseller)
    {
        return view('admin.resellers.form', compact('reseller'));
    }

    public function update(Request $request, Reseller $reseller): RedirectResponse
    {
        $data = $request->validate($this->rules($reseller));

        $reseller->update($this->attributes($data));

        return redirect()->route('admin.resellers.index')->with('status', 'Reseller wurde gespeichert.');
    }

    public function destroy(Reseller $reseller): RedirectResponse
    {
        abort_if($reseller->organizers()->exists(), 422, 'Der Reseller hat noch Veranstalter und kann nicht gelöscht werden.');

        $reseller->delete();

        return redirect()->route('admin.resellers.index')->with('status', 'Reseller wurde gelöscht.');
    }

    private function rules(?Reseller $reseller = null): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:60', Rule::unique('resellers', 'slug')->ignore($reseller)],
            'domain' => ['nullable', 'string', 'max:190', 'regex:/^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', Rule::unique('resellers', 'domain')->ignore($reseller)],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'support_email' => ['nullable', 'email', 'max:190'],
            'max_organizers' => ['nullable', 'integer', 'min:0'],
            'max_events_per_organizer' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function attributes(array $data): array
    {
        return [
            ...collect($data)->only(['name', 'slug', 'brand_name', 'brand_color', 'support_email', 'max_organizers', 'max_events_per_organizer'])->all(),
            'slug' => strtolower($data['slug']),
            'domain' => isset($data['domain']) ? strtolower($data['domain']) : null,
            'allow_registration' => request()->boolean('allow_registration'),
            'is_active' => request()->boolean('is_active'),
        ];
    }
}
