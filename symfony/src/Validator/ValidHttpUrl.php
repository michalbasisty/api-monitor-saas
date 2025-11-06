<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ValidHttpUrl extends Constraint
{
    public string $message = 'The URL "{{ value }}" must be a valid HTTP or HTTPS URL.';
    public bool $requireHttps = false;
    public string $httpsMessage = 'The URL "{{ value }}" must use HTTPS for security.';
}
