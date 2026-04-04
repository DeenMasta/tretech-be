<?php

namespace App\Exceptions;

class ThrottleException extends ApiException
{
    public function __construct(
        int $retryAfter = 60,
        string $message = 'Too many requests',
        array $additional = [],
        ?\Exception $previous = null
    ) {
        parent::__construct(
            message: $message,
            statusCode: 429,
            errors: [],
            additional: array_merge($additional, ['retry_after' => $retryAfter]),
            previous: $previous
        );
    }
}
