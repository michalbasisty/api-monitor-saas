<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidHttpUrlValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidHttpUrl) {
            throw new UnexpectedTypeException($constraint, ValidHttpUrl::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $parsedUrl = parse_url($value);

        if ($parsedUrl === false || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
            return;
        }

        $scheme = strtolower($parsedUrl['scheme']);

        if (!in_array($scheme, ['http', 'https'], true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
            return;
        }

        if ($constraint->requireHttps && $scheme !== 'https') {
            $this->context->buildViolation($constraint->httpsMessage)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
