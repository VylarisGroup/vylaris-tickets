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
        <thead class="border-b border-slate-200 text-slate-500"><tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">Team</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($organizers as $o)
            <tr>
                <td class="px-6 py-3"><a class="font-medium text-brand" href="{{ route('organizer.dashboard', $o) }}">{{ $o->name }}</a></td>
                <td class="px-6 py-3">{{ $o->users_count }}</td>
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
            <tr><td colspan="4" class="px-6 py-4 text-slate-500">Noch keine Veranstalter.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $organizers->links() }}</div>
@endsection
