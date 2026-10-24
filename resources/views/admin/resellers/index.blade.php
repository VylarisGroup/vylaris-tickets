@extends('layouts.app')
@section('title', 'Reseller')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold">Reseller</h1>
    <a href="{{ route('admin.resellers.create') }}" class="btn btn-primary">Reseller anlegen</a>
</div>
<div class="card overflow-x-auto p-0">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 text-slate-500"><tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">Domain</th><th class="px-6 py-3">Veranstalter</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @forelse ($resellers as $r)
            <tr>
                <td class="px-6 py-3 font-medium">{{ $r->name }}</td>
                <td class="px-6 py-3">{{ $r->domain ?? '–' }}</td>
                <td class="px-6 py-3">{{ $r->organizers_count }}@if ($r->max_organizers !== null) / {{ $r->max_organizers }}@endif</td>
                <td class="px-6 py-3">{{ $r->is_active ? 'Aktiv' : 'Gesperrt' }}</td>
                <td class="space-x-3 px-6 py-3 text-right">
                    <a class="text-brand" href="{{ route('reseller.dashboard', $r) }}">Öffnen</a>
                    <a class="text-brand" href="{{ route('admin.resellers.edit', $r) }}">Bearbeiten</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-6 py-4 text-slate-500">Noch keine Reseller.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $resellers->links() }}</div>
@endsection
