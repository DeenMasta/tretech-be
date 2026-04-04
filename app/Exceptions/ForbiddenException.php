<?php

namespace App\Exceptions;

class ForbiddenException extends ApiException
{
    public function __construct(
        string $message = 'Forbidden',
        array $additional = [],
        ?\Exception $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 403,
            errors: [],
            additional: $additional,
            previous: $previous
        );
    }
}
