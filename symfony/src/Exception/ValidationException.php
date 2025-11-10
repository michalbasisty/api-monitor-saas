<?php

namespace App\Exception;

/**
 * Exception thrown for validation errors
 */
class ValidationException extends EcommerceException
{
    /** @var array<string, string[]> */
    private array $fieldErrors = [];

    public function __construct(array $fieldErrors = [])
    {
        $this->fieldErrors = $fieldErrors;
        parent::__construct(
            'VALIDATION_ERROR',
            'Request validation failed',
            400
        );
    }

    /**
     * @return array<string, string[]>
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }
}
