<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Email\AccountSecurityNotificationService;
use App\Services\ImagePreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(
        ProfileUpdateRequest $request,
        ImagePreparationService $images,
        AccountSecurityNotificationService $security,
    ): RedirectResponse {
        $user = $request->user();
        $data = $request->validated();
        $oldAvatar = $user->avatar;
        $newAvatar = null;

        if ($request->hasFile('avatar')) {
            $newAvatar = $images->prepareAvatar($request->file('avatar'));
        }

        $user->full_name = $data['full_name'];

        $passwordChanged = false;
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $passwordChanged = true;
        }

        $pendingVerificationRawToken = null;
        $requestedEmail = isset($data['email']) && trim((string) $data['email']) !== ''
            ? mb_strtolower(trim((string) $data['email']))
            : null;

        if ($requestedEmail !== null && $requestedEmail !== $user->email) {
            $pendingVerificationRawToken = Str::random(64);
            $user->pending_email = $requestedEmail;
            $user->pending_email_token_hash = hash('sha256', $pendingVerificationRawToken);
            $user->pending_email_expires_at = now('UTC')->addHours(max(1, (int) config('email.email_verify_expire_hours', 24)));
        }

        if ($newAvatar) {
            $user->avatar = $newAvatar;
        } elseif ($request->boolean('remove_avatar')) {
            $user->avatar = null;
        }

        $user->save();

        if ($newAvatar && $oldAvatar) {
            $images->deletePreparedImage($oldAvatar);
        } elseif ($request->boolean('remove_avatar') && $oldAvatar) {
            $images->deletePreparedImage($oldAvatar);
        }

        if ($passwordChanged) {
            $security->notifyPasswordChanged($user, $user, 'profile');
        }

        if ($pendingVerificationRawToken !== null && $user->pending_email !== null) {
            $security->notifyEmailChangeVerification($user, $user->pending_email, $pendingVerificationRawToken);

            return redirect()
                ->route('profile.edit')
                ->with('status', "Profile updated. A verification link has been sent to {$user->pending_email} to confirm your new staff email.");
        }

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Profile updated successfully.');
    }
}
