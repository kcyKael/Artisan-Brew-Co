<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffRequest;
use App\Models\User;
use App\Services\ImagePreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $staff = User::query()
            ->withCount('orders')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($sub) use ($like) {
                    $sub->where('username', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
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

    public function store(StaffRequest $request, ImagePreparationService $images): RedirectResponse
    {
        $data = $request->validated();
        $avatarPath = null;

        if ($request->hasFile('avatar')) {
            $avatarPath = $images->prepareAvatar($request->file('avatar'));
        }

        try {
            $staff = new User([
                'username' => $data['username'],
                'full_name' => $data['full_name'],
                'password' => Hash::make($data['password']),
                'avatar' => $avatarPath,
            ]);
            $staff->role = $data['role'];
            $staff->is_active = $request->boolean('is_active', true);
            $staff->save();
        } catch (\Throwable $e) {
            if ($avatarPath) {
                $images->deletePreparedImage($avatarPath);
            }
            throw $e;
        }

        return redirect()
            ->route('staff.index')
            ->with('status', "Staff member '{$staff->full_name}' created.");
    }

    public function edit(User $staff): View
    {
        return view('staff.edit', ['staff' => $staff]);
    }

    public function update(StaffRequest $request, User $staff, ImagePreparationService $images): RedirectResponse
    {
        $data = $request->validated();
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

        $staff->username = $data['username'];
        $staff->full_name = $data['full_name'];
        $staff->role = $newRole;
        $staff->is_active = $newIsActive;

        if (! empty($data['password'])) {
            $staff->password = Hash::make($data['password']);
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
