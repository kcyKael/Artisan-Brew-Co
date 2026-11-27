<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Email\StaffInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class StaffInvitationController extends Controller
{
    public function show(string $token, StaffInvitationService $invitations): View
    {
        $invitation = $invitations->findByRawToken($token);

        return view('auth.accept-invitation', [
            'token' => $token,
            'invitation' => $invitation,
            'isValid' => $invitation !== null && $invitation->isUsable(),
            'effectiveStatus' => $invitation?->effectiveStatus() ?? 'invalid',
        ]);
    }

    public function accept(Request $request, string $token, StaffInvitationService $invitations): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $user = $invitations->accept($token, (string) $validated['password']);
        if (! $user) {
            return back()->withErrors([
                'invitation' => 'This staff invitation is invalid, expired, revoked, or has already been accepted.',
            ]);
        }

        return redirect()
            ->route('login')
            ->with('status', "Account activated for {$user->username}. Sign in with your username and new password.");
    }

    public function resend(Request $request, User $staff, StaffInvitationService $invitations): RedirectResponse
    {
        $throttleKey = 'staff-invite-resend:'.$staff->id.'|'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return back()->withErrors(['staff' => 'Please wait a minute before sending another invitation to this staff member.']);
        }

        if (! filled($staff->email)) {
            return back()->withErrors(['staff' => 'Add a valid email address before sending an invitation.']);
        }

        RateLimiter::hit($throttleKey, 60);

        try {
            $invitations->createAndSend($staff, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['staff' => $e->getMessage()]);
        }

        return back()->with('status', "Fresh staff invitation queued for {$staff->full_name} ({$staff->email}).");
    }

    public function revoke(User $staff, StaffInvitationService $invitations): RedirectResponse
    {
        $revoked = $invitations->revokePendingForUser($staff);

        return back()->with(
            'status',
            $revoked > 0
                ? "Pending invitation revoked for {$staff->full_name}."
                : 'No pending invitation existed for that staff member.'
        );
    }
}
