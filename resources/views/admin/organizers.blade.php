@extends('layouts.app')
@section('title', 'Veranstalter')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<h1 class="mb-6 text-2xl font-bold">Veranstalter</h1>
<div class="card overflow-x-auto p-0">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 text-slate-500"><tr><th class="px-6 py-3">Name</th><th class="px-6 py-3">Reseller</th><th class="px-6 py-3">Status</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
        @foreach ($organizers as $o)
            <tr><td class="px-6 py-3"><a class="font-medium text-brand" href="{{ route('organizer.dashboard', $o) }}">{{ $o->name }}</a></td>
                <td class="px-6 py-3">{{ $o->reseller?->name ?? 'Direkt' }}</td>
                <td class="px-6 py-3">{{ $o->is_active ? 'Aktiv' : 'Gesperrt' }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $organizers->links() }}</div>
@endsection
