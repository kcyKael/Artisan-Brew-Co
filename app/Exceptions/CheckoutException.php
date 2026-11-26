<?php

namespace App\Exceptions;

use RuntimeException;

class CheckoutException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 422,
        public readonly ?string $field = null,
        public readonly ?int $itemIndex = null,
        public readonly ?int $productId = null,
    ) {
        parent::__construct($message, $statusCode);
    }
}
