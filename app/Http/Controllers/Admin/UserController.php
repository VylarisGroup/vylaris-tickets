<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\OrganizerRole;
use App\Enums\ResellerRole;
use App\Models\Organizer;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Benutzerverwaltung der Plattform (CRUD). */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $term = trim((string) $request->query('q'));
        $filter = (string) $request->query('rolle');

        return view('admin.users.index', [
            'users' => User::query()->withCount(['organizers', 'resellers'])
                ->when($term !== '', function ($q) use ($term) {
                    $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                    $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
                })
                ->when($filter === 'admin', fn ($q) => $q->where('is_platform_admin', true))
                ->when($filter === 'ohne', fn ($q) => $q->where('is_platform_admin', false)->whereDoesntHave('organizers')->whereDoesntHave('resellers'))
                ->when($filter === 'mehrere', fn ($q) => $q->has('organizers', '>=', 2))
                ->orderBy('name')->paginate(25)->withQueryString(),
            'filter' => $filter,
            'stats' => [
                'total' => User::count(),
                'admins' => User::where('is_platform_admin', true)->count(),
                'ohne' => User::where('is_platform_admin', false)->whereDoesntHave('organizers')->whereDoesntHave('resellers')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(10)],
        ]);

        $user = User::create($data);
        $user->forceFill(['is_platform_admin' => $request->boolean('is_platform_admin'), 'email_verified_at' => now()])->save();

        return redirect()->route('admin.users.index')->with('status', 'Benutzer wurde angelegt.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', [
            'user' => $user,
            'organizers' => $user->organizers()->get(),
            'resellers' => $user->resellers()->get(),
            'allOrganizers' => Organizer::orderBy('name')->get(['id', 'name']),
            'allResellers' => Reseller::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(10)],
        ]);

        $admin = $request->boolean('is_platform_admin');
        if (! $admin && $user->is_platform_admin && User::where('is_platform_admin', true)->count() <= 1) {
            return back()->withInput()->withErrors(['is_platform_admin' => 'Es muss mindestens ein Plattform-Administrator bleiben.']);
        }
        if (! $admin && $user->is($request->user())) {
            return back()->withInput()->withErrors(['is_platform_admin' => 'Du kannst dir die Administratorrechte nicht selbst entziehen.']);
        }

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->is_platform_admin = $admin;
        $user->save();

        return redirect()->route('admin.users.index')->with('status', 'Benutzer wurde gespeichert.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Du kannst dein eigenes Konto hier nicht löschen.']);
        }
        if ($user->is_platform_admin && User::where('is_platform_admin', true)->count() <= 1) {
            return back()->withErrors(['user' => 'Der letzte Plattform-Administrator kann nicht gelöscht werden.']);
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'Benutzer wurde gelöscht.');
    }

    /** Mandant zuordnen bzw. Rolle ändern (ein Benutzer kann beliebig viele Mandanten haben). */
    public function attach(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['organizer', 'reseller'])],
            'tenant_id' => ['required', 'integer'],
            'role' => ['required', 'string'],
        ]);

        if ($data['type'] === 'organizer') {
            $request->validate(['role' => [Rule::in(array_column(OrganizerRole::cases(), 'value'))]]);
            $tenant = Organizer::findOrFail($data['tenant_id']);
            $user->organizers()->syncWithoutDetaching([$tenant->id => ['role' => $data['role']]]);
        } else {
            $request->validate(['role' => [Rule::in(array_column(ResellerRole::cases(), 'value'))]]);
            $tenant = Reseller::findOrFail($data['tenant_id']);
            $user->resellers()->syncWithoutDetaching([$tenant->id => ['role' => $data['role']]]);
        }

        return redirect()->route('admin.users.edit', $user)->with('status', "Zuordnung zu «{$tenant->name}» gespeichert.");
    }

    public function detach(User $user, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['organizer', 'reseller'], true), 404);
        $type === 'organizer' ? $user->organizers()->detach($id) : $user->resellers()->detach($id);

        return redirect()->route('admin.users.edit', $user)->with('status', 'Zuordnung entfernt.');
    }
}
