<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use App\Support\Access\AccessDenied;
use App\Support\Access\AccountLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class StaffInvitationController extends Controller
{
    public function show(string $token)
    {
        $invitation = StaffInvitation::query()->where('token_hash', hash('sha256', $token))->first();
        abort_if($invitation === null || $invitation->accepted_at !== null || $invitation->expires_at->isPast(), 404);

        return view('staff.invitation', ['token' => $token, 'profile' => $invitation->staffProfile]);
    }

    public function accept(Request $request, string $token, AccountLifecycleService $lifecycle)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        try {
            $lifecycle->acceptInvitation($token, $data['password']);
        } catch (AccessDenied $e) {
            // An unknown, used or expired token looks like a missing page. Other refusals get a plain message.
            abort_if($e->reasonCode === 'invitation_invalid_or_expired', 404);
            $messages = [
                'identity_not_verified' => 'Your identity check is not complete yet. Contact GSK before trying again.',
                'account_exists' => 'An account already exists for this invitation. Contact GSK support.',
                'weak_password' => 'Choose a longer password.',
            ];

            return back()->withErrors(['password' => $messages[$e->reasonCode] ?? 'This invitation cannot be used. Contact GSK support.']);
        }

        return redirect()->route('login')->with('status', 'Your account is ready. Sign in, then set up two-factor authentication.');
    }
}
