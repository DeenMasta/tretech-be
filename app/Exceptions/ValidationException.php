<?php

namespace App\Exceptions;

class ValidationException extends ApiException
{
    public function __construct(
        array $errors,
        string $message = 'Validation failed',
        array $additional = [],
        ?\Exception $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 422,
            errors: $errors,
            additional: $additional,
            previous: $previous
        );
    }
}
