<?php

namespace App\Exceptions;

use RuntimeException;

class DomainException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $statusCode = 400,
        public readonly mixed $details = null,
    ) {
        parent::__construct($message);
    }
}
