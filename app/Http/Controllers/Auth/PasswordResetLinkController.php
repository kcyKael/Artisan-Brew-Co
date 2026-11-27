<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Email\EmailSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, EmailSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
        ]);

        $email = mb_strtolower(trim((string) $validated['email']));
        $throttleKey = 'password-reset:'.$email.'|'.$request->ip();

        if (! RateLimiter::tooManyAttempts($throttleKey, 5) && $settings->featureEnabled('feature_password_reset')) {
            RateLimiter::hit($throttleKey, 60);

            $user = User::query()
                ->where('email', $email)
                ->first();

            if ($user && $user->canReceivePasswordReset()) {
                Password::broker()->sendResetLink(['email' => $user->email]);
            }
        }

        return back()->with(
            'status',
            'If an active staff account matches that verified email address, password recovery instructions have been queued.'
        );
    }
}
