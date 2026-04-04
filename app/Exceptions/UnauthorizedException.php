<?php

namespace App\Exceptions;

class UnauthorizedException extends ApiException
{
    public function __construct(
        string $message = 'Unauthorized',
        array $additional = [],
        ?\Exception $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 401,
            errors: [],
            additional: $additional,
            previous: $previous
        );
    }
}
