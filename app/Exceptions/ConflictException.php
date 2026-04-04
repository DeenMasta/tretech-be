<?php

namespace App\Exceptions;

class ConflictException extends ApiException
{
    public function __construct(
        string $message = 'Resource conflict',
        array $errors = [],
        array $additional = [],
        ?\Exception $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 409,
            errors: $errors,
            additional: $additional,
            previous: $previous
        );
    }
}
