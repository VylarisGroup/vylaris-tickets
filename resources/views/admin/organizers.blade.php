@extends('layouts.app')
@section('title', 'Veranstalter')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-extrabold tracking-tight">Veranstalter</h1>
@error('organizer')<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>@enderror
<form method="GET" class="mb-4 flex gap-2"><input name="q" value="{{ request('q') }}" class="input !w-72" placeholder="Name suchen" aria-label="Suche"><button class="btn btn-ghost">Suchen</button></form>
<div class="card overflow-x-auto p-0">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 text-slate-500"><tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">Reseller</th><th class="px-6 py-3">Events</th><th class="px-6 py-3">Team</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($organizers as $o)
            <tr>
                <td class="px-6 py-3"><a class="font-medium text-brand" href="{{ route('organizer.dashboard', $o) }}">{{ $o->name }}</a></td>
                <td class="px-6 py-3">{{ $o->reseller?->name ?? 'Direkt' }}</td>
                <td class="px-6 py-3">{{ $o->events_count }}</td>
                <td class="px-6 py-3">{{ $o->users_count }}</td>
                <td class="px-6 py-3">{!! $o->is_active ? '<span class="chip !border-emerald-500/40 !text-emerald-600">Aktiv</span>' : '<span class="chip !border-red-500/40 !text-red-500">Gesperrt</span>' !!}</td>
                <td class="px-6 py-3">
                    <div class="flex items-center justify-end gap-3">
                        <a class="text-brand" href="{{ route('admin.organizers.edit', $o) }}">Bearbeiten</a>
                        <form method="POST" action="{{ route('admin.organizers.destroy', $o) }}" onsubmit="return confirm('Veranstalter «{{ $o->name }}» samt Events löschen?')">@csrf @method('DELETE')<button class="text-red-600">Löschen</button></form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-6 py-4 text-slate-500">Keine Veranstalter gefunden.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $organizers->links() }}</div>
@endsection
