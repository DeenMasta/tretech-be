<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

class ApiException extends Exception
{
    protected int $statusCode;
    protected array $errors;
    protected array $additional;

    public function __construct(
        string $message = 'An error occurred',
        int $statusCode = 400,
        array $errors = [],
        array $additional = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->message = $message;
        $this->statusCode = $statusCode;
        $this->errors = $errors;
        $this->additional = $additional;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getAdditional(): array
    {
        return $this->additional;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'status_code' => $this->statusCode,
            'errors' => $this->errors,
            'timestamp' => now()->toIso8601String(),
            ...$this->additional,
        ], $this->statusCode);
    }
}
