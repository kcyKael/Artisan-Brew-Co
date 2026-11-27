<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActiveAccount();
    }

    public function view(User $user, Order $order): bool
    {
        if (! $user->isActiveAccount()) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return (int) $order->sold_by === (int) $user->id;
    }

    public function void(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
