<?php

namespace App\Services;

use App\Models\Order;

final class CheckoutResult
{
    public function __construct(
        public readonly Order $order,
        public readonly bool $wasReplay = false,
    ) {
    }
}
