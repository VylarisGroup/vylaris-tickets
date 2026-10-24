<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Reseller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class InvitationController extends Controller
{
    public function resend(Reseller $reseller, Invitation $invitation): RedirectResponse
    {
        $this->authorizeFor($reseller, $invitation);

        [$new, $token] = Invitation::issue($invitation->organizer, $invitation->email, $invitation->name, $invitation->role);
        Mail::to($new->email)->send(new InvitationMail($new->load('organizer.reseller'), $token));

        return back()->with('status', 'Einladung erneut an '.$new->email.' gesendet.');
    }

    public function destroy(Reseller $reseller, Invitation $invitation): RedirectResponse
    {
        $this->authorizeFor($reseller, $invitation);
        $invitation->delete();

        return back()->with('status', 'Einladung zurückgezogen.');
    }

    private function authorizeFor(Reseller $reseller, Invitation $invitation): void
    {
        abort_unless($invitation->accepted_at === null && $invitation->organizer->reseller_id === $reseller->id, 404);
    }
}
