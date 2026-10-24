@extends('layouts.app')
@section('title', 'Veranstalter')
@section('subnav') @include('reseller._subnav') @endsection
@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold">Veranstalter</h1>
    @if ($reseller->canAddOrganizer())
        <a href="{{ route('reseller.organizers.create', $reseller) }}" class="btn btn-primary">Veranstalter anlegen</a>
    @else
        <span class="text-sm text-slate-500">Limit erreicht ({{ $reseller->max_organizers }})</span>
    @endif
</div>
@error('organizer')<div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>@enderror
<div class="card overflow-x-auto p-0">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 text-slate-500"><tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">Team</th><th class="px-6 py-3">Events</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($organizers as $o)
            <tr>
                <td class="px-6 py-3"><a class="font-medium text-brand" href="{{ route('organizer.dashboard', $o) }}">{{ $o->name }}</a>
                    @foreach ($o->invitations as $inv)
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-slate-500">
                            <span>Einladung an {{ $inv->email }} {{ $inv->expires_at->isPast() ? 'abgelaufen' : 'offen bis '.$inv->expires_at->format('d.m.Y') }}</span>
                            <form method="POST" action="{{ route('reseller.invitations.resend', [$reseller, $inv]) }}">@csrf<button class="text-brand">Erneut senden</button></form>
                            <form method="POST" action="{{ route('reseller.invitations.destroy', [$reseller, $inv]) }}">@csrf @method('DELETE')<button class="text-red-600">Zurückziehen</button></form>
                        </div>
                    @endforeach</td>
                <td class="px-6 py-3">{{ $o->users_count }}</td>
                @php($ep = \App\Models\Reseller::percent($o->events_count, $reseller->max_events_per_organizer))
                <td class="px-6 py-3 {{ $ep === null ? '' : ($ep >= 100 ? 'font-medium text-red-600' : ($ep >= 80 ? 'font-medium text-amber-700' : '')) }}">{{ $o->events_count }}@if ($reseller->max_events_per_organizer !== null)<span class="text-slate-400"> / {{ $reseller->max_events_per_organizer }}</span>@endif</td>
                <td class="px-6 py-3">{{ $o->is_active ? 'Aktiv' : 'Gesperrt' }}</td>
                <td class="px-6 py-3 text-right">
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('reseller.organizers.edit', [$reseller, $o]) }}" class="text-brand">Bearbeiten</a>
                        <form method="POST" action="{{ route('reseller.organizers.toggle', [$reseller, $o]) }}">@csrf @method('PATCH')
                            <button class="text-brand">{{ $o->is_active ? 'Sperren' : 'Aktivieren' }}</button>
                        </form>
                        <form method="POST" action="{{ route('reseller.organizers.destroy', [$reseller, $o]) }}" onsubmit="return confirm('Veranstalter «{{ $o->name }}» samt Events löschen?')">@csrf @method('DELETE')<button class="text-red-600">Löschen</button></form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-6 py-4 text-slate-500">Noch keine Veranstalter.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $organizers->links() }}</div>
@endsection
