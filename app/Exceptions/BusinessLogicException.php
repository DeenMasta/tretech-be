<?php

namespace App\Exceptions;

class BusinessLogicException extends ApiException
{
    public function __construct(
        string $message = 'Business logic error',
        array $errors = [],
        array $additional = [],
        ?\Exception $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 400,
            errors: $errors,
            additional: $additional,
            previous: $previous
        );
    }
}
