@extends('layouts.app')
@section('title', 'Benachrichtigungen')
@section('content')
<h1 class="mb-1 text-2xl font-extrabold tracking-tight">Benachrichtigungen</h1>
<p class="mb-6 text-sm text-slate-500">Push-Meldungen direkt aufs Handy oder in den Browser, auch wenn die App geschlossen ist.</p>

<div class="grid max-w-5xl gap-6 lg:grid-cols-2">
    <div class="card space-y-4" x-data="pushSettings(@js(['url' => route('push.subscribe'), 'key' => $vapidKey]))">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold">Dieses Gerät</h2>
            <span class="chip" :class="subscribed ? '!border-emerald-500/40 !text-emerald-500' : ''"><span class="h-2 w-2 rounded-full" :class="subscribed ? 'bg-[#059669]' : 'bg-slate-400'"></span><span x-text="subscribed ? 'Aktiv' : 'Aus'"></span></span>
        </div>

        <template x-if="needsInstall">
            <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">Auf dem iPhone und iPad funktioniert Push nur in der installierten App: Teilen-Symbol <span aria-hidden="true">⎙</span> antippen, «Zum Home-Bildschirm» wählen und die App von dort öffnen.</div>
        </template>
        <template x-if="!supported && !needsInstall">
            <div class="rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-500">Dieser Browser unterstützt keine Push-Benachrichtigungen (oder die Seite läuft nicht über HTTPS).</div>
        </template>
        <p x-show="permission === 'denied'" x-cloak class="text-sm text-red-600">Benachrichtigungen sind für diese Seite blockiert. Erlaube sie in den Einstellungen deines Browsers bzw. Geräts.</p>
        <p x-show="error" x-cloak class="text-sm text-red-600" x-text="error"></p>

        <div class="flex flex-wrap gap-2" x-show="supported" x-cloak>
            <button type="button" class="btn btn-primary" x-show="!subscribed" @click="enable()" :disabled="busy"><x-icon name="bell" :size="16" />Auf diesem Gerät aktivieren</button>
            <button type="button" class="btn btn-ghost" x-show="subscribed" @click="disable()" :disabled="busy">Auf diesem Gerät ausschalten</button>
            <form method="POST" action="{{ route('push.test') }}" x-show="subscribed">@csrf<button class="btn btn-ghost">Test senden</button></form>
        </div>

        <div class="border-t border-slate-200/70 pt-4">
            <h3 class="mb-2 text-sm font-semibold">Angemeldete Geräte</h3>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($devices as $d)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <div><div class="font-medium">{{ $d->device ?: 'Gerät' }}</div><div class="text-xs text-slate-500">Seit {{ $d->created_at->format('d.m.Y') }}@if ($d->last_used_at) · zuletzt {{ $d->last_used_at->format('d.m.Y H:i') }}@endif</div></div>
                        <form method="POST" action="{{ route('push.destroy', $d->id) }}" onsubmit="return confirm('Gerät entfernen?')">@csrf @method('DELETE')<button class="text-red-600">Entfernen</button></form>
                    </li>
                @empty
                    <li class="py-2 text-slate-500">Noch kein Gerät angemeldet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <form method="POST" action="{{ route('push.update') }}" class="card space-y-4">@csrf @method('PUT')
        <h2 class="font-semibold">Wann soll ich benachrichtigt werden?</h2>
        <p class="text-sm text-slate-500">Gilt für alle deine Geräte. Meldungen für Veranstalter gehen an Inhaber und Verwalter.</p>
        <div class="divide-y divide-slate-100">
            @foreach ($categories as $key => [$title, $desc, $adminOnly])
                <label class="flex cursor-pointer items-start gap-3 py-3">
                    <input type="checkbox" name="prefs[{{ $key }}]" value="1" @checked($user->wantsPush($key)) class="mt-1 rounded border-slate-300">
                    <span class="min-w-0"><span class="block text-sm font-medium">{{ $title }}@if ($adminOnly) <span class="chip ml-1 !px-2 !py-0 text-[10px]">Admin</span>@endif</span><span class="block text-xs text-slate-500">{{ $desc }}</span></span>
                </label>
            @endforeach
        </div>
        <button class="btn btn-primary">Speichern</button>
    </form>
</div>
@endsection
