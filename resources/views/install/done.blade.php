@extends('install.layout')
@section('step')
<div>
    <h1 class="text-2xl font-bold">Fast geschafft!</h1>
    <p class="mt-1 text-sm text-slate-600">Alles Wichtige ist eingerichtet. Mit einem Klick schliesst du die Einrichtung ab. Danach ist dieser Assistent gesperrt.</p>
</div>

<div class="rounded-lg border border-slate-200 p-4 text-sm">
    <div class="mb-2 font-semibold">Zwei Dinge solltest du danach noch erledigen</div>
    <ol class="list-decimal space-y-3 pl-5 text-slate-700">
        <li>
            <strong>Cron-Job anlegen</strong> (Plesk: «Geplante Aufgaben»), jede Minute. Er lässt Reservierungen ablaufen und benachrichtigt die Warteliste:
            <code class="mt-1 block select-all break-all rounded bg-slate-100 p-2 text-xs">{{ $cron }}</code>
        </li>
        <li>
            <strong>Zahlungsarten hinterlegen</strong>: Mollie- oder Stripe-Schlüssel bzw. IBAN beim Veranstalter unter «Einstellungen».
            @unless ($organizer) Lege dafür zuerst im Admin-Bereich einen Veranstalter an. @endunless
        </li>
        @unless ($mailConfigured)<li><strong>E-Mail</strong> ist noch nicht eingerichtet. Trage die SMTP-Zugangsdaten (<code>MAIL_*</code>) in der Datei <code>.env</code> ein.</li>@endunless
    </ol>
    <p class="mt-3 text-xs text-slate-500">Im Admin-Bereich zeigt dir ein Systemstatus jederzeit, was noch offen ist.</p>
</div>

<form method="POST" action="{{ route('install.finish') }}" class="flex justify-end">@csrf
    <button class="btn btn-primary">Einrichtung abschliessen</button>
</form>
@endsection
