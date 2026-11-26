<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ImagePreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request, ImagePreparationService $images): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $oldAvatar = $user->avatar;
        $newAvatar = null;

        if ($request->hasFile('avatar')) {
            $newAvatar = $images->prepareAvatar($request->file('avatar'));
        }

        $user->full_name = $data['full_name'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
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

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Profile updated successfully.');
    }
}
