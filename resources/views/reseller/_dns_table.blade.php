{{-- Erwartet $records: Liste aus type, name, value, ttl, note, alternative --}}
<div class="overflow-x-auto rounded-lg border border-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-3 py-2">Typ</th><th class="px-3 py-2">Name / Host</th><th class="px-3 py-2">Wert / Ziel</th><th class="px-3 py-2">TTL</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($records as $r)
                <tr>
                    <td class="px-3 py-2 font-mono font-semibold">{{ $r['type'] }}@if ($r['alternative'])<span class="ml-1 text-xs font-normal text-slate-500">(Alternative)</span>@endif</td>
                    <td class="px-3 py-2 font-mono"><span class="select-all">{{ $r['name'] }}</span></td>
                    <td class="px-3 py-2 font-mono"><span class="select-all break-all">{{ $r['value'] }}</span></td>
                    <td class="px-3 py-2 font-mono">{{ $r['ttl'] }}</td>
                </tr>
                @if ($r['note'])<tr><td colspan="4" class="bg-slate-50/60 px-3 py-1.5 text-xs text-slate-500">{{ $r['note'] }}</td></tr>@endif
            @endforeach
        </tbody>
    </table>
</div>
