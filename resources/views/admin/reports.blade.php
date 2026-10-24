@extends('layouts.app')
@section('title', 'Meldungen')
@section('subnav') @include('admin._subnav') @endsection
@section('content')
<h1 class="mb-4 text-2xl font-bold">Gemeldete Events</h1>
<div class="mb-6 flex gap-2 text-sm">
    @foreach (['open' => 'Offen ('.$openCount.')', 'resolved' => 'Erledigt', 'dismissed' => 'Abgewiesen', 'all' => 'Alle'] as $k => $l)
        <a href="{{ route('admin.reports.index', ['status' => $k]) }}" class="rounded-full px-3 py-1 {{ $status === $k ? 'bg-brand text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">{{ $l }}</a>
    @endforeach
</div>
<div class="space-y-4">
    @forelse ($reports as $r)
        <div class="card space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <div class="font-semibold">{{ $r->event->title }}</div>
                    <div class="text-sm text-slate-500">{{ $r->event->organizer->name }} · <a class="text-brand hover:underline" target="_blank" href="{{ route('shop.event', [$r->event->organizer, $r->event]) }}">Seite ansehen</a></div>
                </div>
                <div class="text-right text-sm">
                    <span class="rounded-full bg-slate-100 px-3 py-1">{{ $r->reasonLabel() }}</span>
                    @if ($r->event->is_blocked)<span class="ml-1 rounded-full bg-red-100 px-3 py-1 text-red-700">gesperrt</span>@endif
                </div>
            </div>
            @if ($r->message)<p class="whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm">{{ $r->message }}</p>@endif
            <div class="text-xs text-slate-500">{{ $r->created_at->format('d.m.Y H:i') }}@if ($r->reporter_email) · {{ $r->reporter_email }}@endif · Status: {{ ['open' => 'offen', 'resolved' => 'erledigt', 'dismissed' => 'abgewiesen'][$r->status] ?? $r->status }}</div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($r->status === 'open')
                    <form method="POST" action="{{ route('admin.reports.update', $r) }}" class="flex flex-wrap items-center gap-2">@csrf @method('PATCH')
                        <input name="reason" class="input w-64" placeholder="Sperrgrund (optional)">
                        <button name="action" value="block" class="btn btn-danger" onclick="return confirm('Event sperren? Der Verkauf wird sofort gestoppt.')">Event sperren</button>
                        <button name="action" value="resolve" class="btn btn-ghost">Erledigt</button>
                        <button name="action" value="dismiss" class="btn btn-ghost">Abweisen</button>
                    </form>
                @endif
                @if ($r->event->is_blocked)
                    <form method="POST" action="{{ route('admin.reports.update', $r) }}">@csrf @method('PATCH')<button name="action" value="unblock" class="btn btn-ghost">Sperre aufheben</button></form>
                @endif
            </div>
        </div>
    @empty
        <div class="card text-sm text-slate-500">Keine Meldungen.</div>
    @endforelse
</div>
<div class="mt-4">{{ $reports->links() }}</div>
@endsection
