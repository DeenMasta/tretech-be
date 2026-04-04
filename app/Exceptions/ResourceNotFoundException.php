<?php

namespace App\Exceptions;

class ResourceNotFoundException extends ApiException
{
    public function __construct(
        string $resource = 'Resource',
        mixed $identifier = null,
        array $additional = [],
        ?\Exception $previous = null
    ) {
        $message = "$resource not found";
        if ($identifier) {
            $message .= " (ID: $identifier)";
        }

        parent::__construct(
            message: $message,
            statusCode: 404,
            errors: [],
            additional: $additional,
            previous: $previous
        );
    }
}
