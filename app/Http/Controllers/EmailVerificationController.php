<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Email\AccountSecurityNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $token, AccountSecurityNotificationService $security): RedirectResponse
    {
        $user = $request->user();
        $hash = hash('sha256', trim($token));

        $result = DB::transaction(function () use ($user, $hash): array {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (
                empty($locked->pending_email)
                || empty($locked->pending_email_token_hash)
                || ! hash_equals($locked->pending_email_token_hash, $hash)
            ) {
                return ['ok' => false, 'reason' => 'Email verification link is invalid or already used.'];
            }

            if ($locked->pending_email_expires_at === null || $locked->pending_email_expires_at->isPast()) {
                $locked->pending_email = null;
                $locked->pending_email_token_hash = null;
                $locked->pending_email_expires_at = null;
                $locked->save();

                return ['ok' => false, 'reason' => 'Email verification link expired. Please submit your new email again.'];
            }

            $emailInUse = User::query()
                ->where('email', $locked->pending_email)
                ->where('id', '!=', $locked->id)
                ->exists();

            if ($emailInUse) {
                return ['ok' => false, 'reason' => 'That email address is already assigned to another staff account.'];
            }

            $oldEmail = $locked->email;
            $newEmail = $locked->pending_email;

            $locked->email = $newEmail;
            $locked->email_verified_at = now('UTC');
            $locked->pending_email = null;
            $locked->pending_email_token_hash = null;
            $locked->pending_email_expires_at = null;
            $locked->save();

            return ['ok' => true, 'old' => $oldEmail, 'new' => $newEmail, 'user' => $locked];
        });

        if (! $result['ok']) {
            return redirect()
                ->route('profile.edit')
                ->withErrors(['email' => $result['reason']]);
        }

        if (! empty($result['old']) && $result['old'] !== $result['new']) {
            $security->notifyPreviousEmailOfChange($result['user'], $result['old'], $result['new']);
        }

        return redirect()
            ->route('profile.edit')
            ->with('status', "Your verified staff email has been updated to {$result['new']}.");
    }
}
