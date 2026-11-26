<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActiveAccount() && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isActiveAccount() && $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isActiveAccount() && $user->isAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isActiveAccount() && $user->isAdmin() && (int) $user->id !== (int) $target->id;
    }
}
