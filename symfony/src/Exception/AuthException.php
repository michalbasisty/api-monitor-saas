<?php

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

class AuthException extends ApiException
{
    public function __construct(string $message, \Throwable $previous = null)
    {
        parent::__construct($message, Response::HTTP_UNAUTHORIZED, $previous);
    }
}
