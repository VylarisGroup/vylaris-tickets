@extends('layouts.app')
@section('title', 'Benutzer')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-extrabold tracking-tight">Benutzer</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />Benutzer anlegen</a>
</div>
@error('user')<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>@enderror
<div class="mb-6 grid gap-3 sm:grid-cols-3">
    <div class="card !p-4"><div class="text-xs uppercase tracking-wider text-slate-500">Benutzer</div><div class="text-2xl font-extrabold">{{ $stats['total'] }}</div></div>
    <div class="card !p-4"><div class="text-xs uppercase tracking-wider text-slate-500">Administratoren</div><div class="text-2xl font-extrabold">{{ $stats['admins'] }}</div></div>
    <div class="card !p-4"><div class="text-xs uppercase tracking-wider text-slate-500">Ohne Zuordnung</div><div class="text-2xl font-extrabold">{{ $stats['ohne'] }}</div></div>
</div>
<form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
    <input name="q" value="{{ request('q') }}" class="input !w-72" placeholder="Name oder E-Mail" aria-label="Suche">
    <select name="rolle" class="input !w-auto" aria-label="Filter" onchange="this.form.submit()">
        <option value="">Alle</option>
        <option value="admin" @selected($filter === 'admin')>Administratoren</option>
        <option value="mehrere" @selected($filter === 'mehrere')>Mehrere Veranstalter</option>
        <option value="ohne" @selected($filter === 'ohne')>Ohne Zuordnung</option>
    </select>
    <button class="btn btn-ghost">Suchen</button>
    @if (request()->hasAny(['q', 'rolle']))<a href="{{ route('admin.users.index') }}" class="text-sm text-slate-500 hover:text-brand">Zurücksetzen</a>@endif
</form>
<div class="card overflow-x-auto p-0">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 text-slate-500"><tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">E-Mail</th><th class="px-6 py-3">Rolle</th><th class="px-6 py-3">Zugänge</th><th class="px-6 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($users as $u)
            <tr>
                <td class="px-6 py-3"><div class="flex items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand/15 text-xs font-bold text-brand">{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</span><span class="font-medium">{{ $u->name }}@if ($u->is(auth()->user())) <span class="text-xs text-slate-500">(du)</span>@endif</span></div></td>
                <td class="px-6 py-3">{{ $u->email }}</td>
                <td class="px-6 py-3">@if ($u->is_platform_admin)<span class="chip !border-brand/40 !text-brand">Administrator</span>@else<span class="text-slate-500">Benutzer</span>@endif</td>
                <td class="px-6 py-3 text-slate-500">{{ $u->organizers_count }} Veranstalter · {{ $u->resellers_count }} Reseller</td>
                <td class="px-6 py-3">
                    <div class="flex items-center justify-end gap-3">
                        <a class="text-brand" href="{{ route('admin.users.edit', $u) }}">Bearbeiten</a>
                        @unless ($u->is(auth()->user()))
                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" onsubmit="return confirm('Benutzer «{{ $u->name }}» löschen?')">@csrf @method('DELETE')<button class="text-red-600">Löschen</button></form>
                        @endunless
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-6 py-4 text-slate-500">Keine Benutzer gefunden.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
