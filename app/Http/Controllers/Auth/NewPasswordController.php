<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Email\AccountSecurityNotificationService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request, AccountSecurityNotificationService $security): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $email = mb_strtolower(trim((string) $request->input('email')));
        $candidate = User::query()->where('email', $email)->first();

        if (! $candidate || ! $candidate->canReceivePasswordReset()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This password reset link is invalid, expired, or ineligible for this account.']);
        }

        $status = Password::broker()->reset(
            [
                'email' => $email,
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now('UTC'),
                ])->save();

                DB::table('sessions')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        $candidate->refresh();
        $security->notifyPasswordChanged($candidate, $candidate, 'reset');

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset. Please sign in with your username and new password.');
    }
}
