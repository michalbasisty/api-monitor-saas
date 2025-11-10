<?php

namespace App\Exception;

/**
 * Base exception for e-commerce module
 */
class EcommerceException extends \DomainException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $statusCode = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->code;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
