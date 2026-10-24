<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Organizer;
use App\Models\Voucher;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoucherController extends Controller
{
    public function index(Organizer $organizer)
    {
        return view('organizer.vouchers', [
            'organizer' => $organizer,
            'vouchers' => $organizer->vouchers()->with('event')->latest()->get(),
            'events' => $organizer->events()->orderByDesc('starts_at')->get(),
        ]);
    }

    public function store(Request $request, Organizer $organizer): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('vouchers', 'code')->where('organizer_id', $organizer->id)],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'string'],
            'event_id' => ['nullable', Rule::exists('events', 'id')->where('organizer_id', $organizer->id)],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ], ['code.regex' => 'Der Code darf nur Grossbuchstaben, Zahlen, Bindestrich und Unterstrich enthalten.']);

        $value = $data['type'] === 'percent' ? (int) $data['value'] : Money::toCents($data['value']);

        if ($value === null || $value < 1 || ($data['type'] === 'percent' && $value > 100)) {
            return back()->withInput()->withErrors(['value' => $data['type'] === 'percent' ? 'Prozentwert zwischen 1 und 100 angeben.' : 'Bitte einen gültigen Betrag eingeben.']);
        }

        $organizer->vouchers()->create([...$data, 'value' => $value]);

        return back()->with('status', 'Gutschein wurde angelegt.');
    }

    public function edit(Organizer $organizer, Voucher $voucher)
    {
        abort_unless($voucher->organizer_id === $organizer->id, 404);

        return view('organizer.voucher-edit', [
            'organizer' => $organizer, 'voucher' => $voucher,
            'events' => $organizer->events()->orderByDesc('starts_at')->get(),
        ]);
    }

    public function update(Request $request, Organizer $organizer, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->organizer_id === $organizer->id, 404);

        $data = $request->validate([
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'string'],
            'event_id' => ['nullable', Rule::exists('events', 'id')->where('organizer_id', $organizer->id)],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $value = $data['type'] === 'percent' ? (int) $data['value'] : Money::toCents($data['value']);
        if ($value === null || $value < 1 || ($data['type'] === 'percent' && $value > 100)) {
            return back()->withInput()->withErrors(['value' => $data['type'] === 'percent' ? 'Prozentwert zwischen 1 und 100 angeben.' : 'Bitte einen gültigen Betrag eingeben.']);
        }
        if (! empty($data['max_uses']) && $data['max_uses'] < $voucher->used_count) {
            return back()->withInput()->withErrors(['max_uses' => 'Der Gutschein wurde schon '.$voucher->used_count.'-mal eingelöst.']);
        }

        $voucher->update([...$data, 'value' => $value, 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('organizer.vouchers.index', $organizer)->with('status', 'Gutschein wurde gespeichert.');
    }

    public function destroy(Organizer $organizer, Voucher $voucher): RedirectResponse
    {
        $voucher->delete();

        return back()->with('status', 'Gutschein wurde gelöscht.');
    }
}
