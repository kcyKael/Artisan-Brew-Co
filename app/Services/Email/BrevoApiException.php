<?php

namespace App\Services\Email;

use RuntimeException;

class BrevoApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $errorCode = null,
        public readonly bool $isRetryable = false,
        public readonly bool $isUncertain = false,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
