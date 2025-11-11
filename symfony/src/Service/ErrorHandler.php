<?php

namespace App\Service;

use App\Exception\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centralized error handling service for consistent error responses
 */
class ErrorHandler
{
    // Error codes - organize logically
    public const ERR_VALIDATION = 'VALIDATION_ERROR';
    public const ERR_NOT_FOUND = 'NOT_FOUND';
    public const ERR_UNAUTHORIZED = 'UNAUTHORIZED';
    public const ERR_FORBIDDEN = 'FORBIDDEN';
    public const ERR_CONFLICT = 'CONFLICT';
    public const ERR_INTERNAL = 'INTERNAL_ERROR';
    public const ERR_SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    public const ERR_RATE_LIMITED = 'RATE_LIMITED';

    /**
     * Map error codes to HTTP status codes
     */
    private const ERROR_STATUS_MAP = [
        self::ERR_VALIDATION => Response::HTTP_BAD_REQUEST,
        self::ERR_NOT_FOUND => Response::HTTP_NOT_FOUND,
        self::ERR_UNAUTHORIZED => Response::HTTP_UNAUTHORIZED,
        self::ERR_FORBIDDEN => Response::HTTP_FORBIDDEN,
        self::ERR_CONFLICT => Response::HTTP_CONFLICT,
        self::ERR_INTERNAL => Response::HTTP_INTERNAL_SERVER_ERROR,
        self::ERR_SERVICE_UNAVAILABLE => Response::HTTP_SERVICE_UNAVAILABLE,
        self::ERR_RATE_LIMITED => Response::HTTP_TOO_MANY_REQUESTS,
    ];

    /**
     * Creates an ApiException with consistent error structure
     */
    public static function createException(
        string $code,
        string $message,
        int $statusCode = null,
        array $details = []
    ): ApiException {
        $statusCode = $statusCode ?? self::ERROR_STATUS_MAP[$code] ?? Response::HTTP_INTERNAL_SERVER_ERROR;
        
        return new ApiException(
            self::formatErrorMessage($code, $message, $details),
            $statusCode
        );
    }

    /**
     * Formats error message consistently
     */
    private static function formatErrorMessage(string $code, string $message, array $details = []): string
    {
        $errorStructure = [
            'code' => $code,
            'message' => $message,
        ];

        if (!empty($details)) {
            $errorStructure['details'] = $details;
        }

        return json_encode($errorStructure);
    }

    /**
     * Handles validation errors
     */
    public static function handleValidationError(array $errors): ApiException
    {
        return self::createException(
            self::ERR_VALIDATION,
            'Validation failed',
            Response::HTTP_BAD_REQUEST,
            ['errors' => $errors]
        );
    }

    /**
     * Handles database/resource not found
     */
    public static function handleNotFound(string $resource, $id): ApiException
    {
        return self::createException(
            self::ERR_NOT_FOUND,
            "{$resource} not found",
            Response::HTTP_NOT_FOUND,
            ['resource' => $resource, 'id' => $id]
        );
    }

    /**
     * Handles authentication failures
     */
    public static function handleUnauthorized(string $reason = 'Invalid credentials'): ApiException
    {
        return self::createException(
            self::ERR_UNAUTHORIZED,
            $reason
        );
    }

    /**
     * Handles authorization failures
     */
    public static function handleForbidden(string $reason = 'Access denied'): ApiException
    {
        return self::createException(
            self::ERR_FORBIDDEN,
            $reason
        );
    }

    /**
     * Handles conflict errors (e.g., duplicate entries)
     */
    public static function handleConflict(string $message): ApiException
    {
        return self::createException(
            self::ERR_CONFLICT,
            $message
        );
    }

    /**
     * Handles internal server errors safely
     */
    public static function handleInternalError(string $logMessage, bool $includeTechnicalDetails = false): ApiException
    {
        // Log the detailed error securely
        // error_log($logMessage);

        $message = 'An internal error occurred. Please try again later.';
        $details = [];

        if ($includeTechnicalDetails) {
            $details = ['error' => $logMessage];
        }

        return self::createException(
            self::ERR_INTERNAL,
            $message,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $details
        );
    }

    /**
     * Handles service unavailable
     */
    public static function handleServiceUnavailable(string $service): ApiException
    {
        return self::createException(
            self::ERR_SERVICE_UNAVAILABLE,
            "{$service} is temporarily unavailable",
            Response::HTTP_SERVICE_UNAVAILABLE
        );
    }

    /**
     * Handles rate limiting
     */
    public static function handleRateLimited(int $retryAfter = null): ApiException
    {
        $details = [];
        if ($retryAfter) {
            $details['retry_after'] = $retryAfter;
        }

        return self::createException(
            self::ERR_RATE_LIMITED,
            'Too many requests. Please try again later.',
            Response::HTTP_TOO_MANY_REQUESTS,
            $details
        );
    }
}
