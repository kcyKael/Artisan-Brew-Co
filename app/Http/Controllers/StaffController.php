<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffRequest;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\Email\AccountSecurityNotificationService;
use App\Services\Email\StaffInvitationService;
use App\Services\ImagePreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $staff = User::query()
            ->withCount('orders')
            ->with('latestInvitation')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('username', 'like', $like)
                        ->orWhere('full_name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderByRaw("CASE WHEN role = 'admin' THEN 0 ELSE 1 END")
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        return view('staff.index', [
            'staffMembers' => $staff,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('staff.create');
    }

    public function store(
        StaffRequest $request,
        ImagePreparationService $images,
        StaffInvitationService $invitations,
    ): RedirectResponse {
        $data = $request->validated();
        $avatarPath = null;
        $inviteMode = $request->boolean('invite_mode');
        $email = isset($data['email']) && trim((string) $data['email']) !== ''
            ? mb_strtolower(trim((string) $data['email']))
            : null;

        if ($request->hasFile('avatar')) {
            $avatarPath = $images->prepareAvatar($request->file('avatar'));
        }

        try {
            $staff = new User([
                'username' => $data['username'],
                'full_name' => $data['full_name'],
                'email' => $email,
                'password' => Hash::make($inviteMode ? Str::random(40) : (string) $data['password']),
                'avatar' => $avatarPath,
            ]);
            $staff->role = $data['role'];
            $staff->is_active = $inviteMode ? false : $request->boolean('is_active', true);
            $staff->save();
        } catch (\Throwable $e) {
            if ($avatarPath) {
                $images->deletePreparedImage($avatarPath);
            }
            throw $e;
        }

        if ($inviteMode) {
            try {
                $invitations->createAndSend($staff, $request->user());
            } catch (\Throwable $e) {
                return redirect()
                    ->route('staff.index')
                    ->withErrors(['staff' => "Staff account '{$staff->full_name}' created as pending, but invitation could not be queued: {$e->getMessage()}"]);
            }

            return redirect()
                ->route('staff.index')
                ->with('status', "Staff invitation sent to {$staff->email} for '{$staff->full_name}'.");
        }

        return redirect()
            ->route('staff.index')
            ->with('status', "Staff member '{$staff->full_name}' created.");
    }

    public function edit(User $staff): View
    {
        $staff->load('latestInvitation');

        return view('staff.edit', ['staff' => $staff]);
    }

    public function update(
        StaffRequest $request,
        User $staff,
        ImagePreparationService $images,
        AccountSecurityNotificationService $security,
        StaffInvitationService $invitations,
    ): RedirectResponse {
        $data = $request->validated();
        $oldRole = $staff->role;
        $wasActive = $staff->is_active;
        $newRole = $data['role'];
        $newIsActive = $request->boolean('is_active', false);

        $wasActiveAdmin = $staff->role === User::ROLE_ADMIN && $staff->is_active;
        $willRemainActiveAdmin = $newRole === User::ROLE_ADMIN && $newIsActive;

        if ($wasActiveAdmin && ! $willRemainActiveAdmin) {
            $otherActiveAdmins = User::query()
                ->where('role', User::ROLE_ADMIN)
                ->where('is_active', true)
                ->where('id', '!=', $staff->id)
                ->count();

            if ($otherActiveAdmins === 0) {
                return back()
                    ->withInput()
                    ->withErrors(['role' => 'Cannot demote or deactivate the final active administrator account.']);
            }
        }

        $oldAvatar = $staff->avatar;
        $newAvatar = null;
        if ($request->hasFile('avatar')) {
            $newAvatar = $images->prepareAvatar($request->file('avatar'));
        }

        $newEmail = isset($data['email']) && trim((string) $data['email']) !== ''
            ? mb_strtolower(trim((string) $data['email']))
            : null;
        $emailChanged = $newEmail !== $staff->email;

        $staff->username = $data['username'];
        $staff->full_name = $data['full_name'];
        $staff->email = $newEmail;
        if ($emailChanged) {
            $staff->email_verified_at = $newEmail ? now('UTC') : null;
        }
        $staff->role = $newRole;
        $staff->is_active = $newIsActive;

        $passwordChanged = false;
        if (! empty($data['password'])) {
            $staff->password = Hash::make($data['password']);
            $passwordChanged = true;
        }

        if ($newAvatar) {
            $staff->avatar = $newAvatar;
        } elseif ($request->boolean('remove_avatar')) {
            $staff->avatar = null;
        }

        $staff->save();

        if ($newAvatar && $oldAvatar) {
            $images->deletePreparedImage($oldAvatar);
        } elseif ($request->boolean('remove_avatar') && $oldAvatar) {
            $images->deletePreparedImage($oldAvatar);
        }

        if (! $staff->is_active) {
            DB::table('sessions')->where('user_id', $staff->id)->delete();
            $invitations->revokePendingForUser($staff);
        }

        if ($passwordChanged) {
            $security->notifyPasswordChanged($staff, $request->user(), 'admin_update');
        }
        if ($oldRole !== $newRole) {
            $security->notifyRoleChanged($staff, $oldRole, $newRole, $request->user());
        }
        if ($wasActive && ! $newIsActive) {
            $security->notifyAccountDeactivated($staff, $request->user());
        }

        return redirect()
            ->route('staff.index')
            ->with('status', "Staff member '{$staff->full_name}' updated.");
    }

    public function destroy(Request $request, User $staff, ImagePreparationService $images): RedirectResponse
    {
        if ((int) $request->user()->id === (int) $staff->id) {
            return back()->withErrors(['staff' => 'You cannot delete your own active staff account.']);
        }

        if ($staff->role === User::ROLE_ADMIN && $staff->is_active) {
            $otherActiveAdmins = User::query()
                ->where('role', User::ROLE_ADMIN)
                ->where('is_active', true)
                ->where('id', '!=', $staff->id)
                ->count();

            if ($otherActiveAdmins === 0) {
                return back()->withErrors(['staff' => 'Cannot remove the final active administrator account.']);
            }
        }

        $name = $staff->full_name;
        DB::table('sessions')->where('user_id', $staff->id)->delete();
        StaffInvitation::query()
            ->where('user_id', $staff->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'revoked',
                'revoked_at' => now('UTC'),
            ]);

        if ($staff->orders()->exists() || $staff->sales()->exists()) {
            $staff->is_active = false;
            $staff->save();
            $staff->delete();

            return redirect()
                ->route('staff.index')
                ->with('status', "Staff member '{$name}' removed from active staff while preserving historical sales records.");
        }

        $oldAvatar = $staff->avatar;
        $staff->forceDelete();
        $images->deletePreparedImage($oldAvatar);

        return redirect()
            ->route('staff.index')
            ->with('status', "Staff member '{$name}' deleted permanently.");
    }
}
