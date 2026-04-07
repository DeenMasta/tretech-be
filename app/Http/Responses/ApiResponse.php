<?php

namespace App\Http\Responses;

class ApiResponse
{
    /**
     * Create a success response.
     */
    public static function success(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200,
        array $additional = []
    ): array {
        return array_merge([
            'success' => true,
            'message' => $message,
            'status_code' => $statusCode,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ], $additional);
    }

    /**
     * Create an error response.
     */
    public static function error(
        string $message = 'An error occurred',
        int $statusCode = 400,
        array $errors = [],
        array $additional = []
    ): array {
        return array_merge([
            'success' => false,
            'message' => $message,
            'status_code' => $statusCode,
            'errors' => $errors,
            'timestamp' => now()->toIso8601String(),
        ], $additional);
    }

    /**
     * Create a paginated response.
     */
    public static function paginated(
        mixed $items,
        int $total,
        int $perPage,
        int $currentPage,
        string $message = 'Success',
        int $statusCode = 200
    ): array {
        $lastPage = ceil($total / $perPage);

        return [
            'success' => true,
            'message' => $message,
            'status_code' => $statusCode,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'from' => ($currentPage - 1) * $perPage + 1,
                'to' => min($currentPage * $perPage, $total),
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Create a cursor-paginated response (no total count — scales to huge tables).
     */
    public static function cursorPaginated(
        mixed $items,
        int $perPage,
        ?string $nextCursor,
        ?string $prevCursor,
        string $message = 'Success',
        int $statusCode = 200
    ): array {
        return [
            'success'     => true,
            'message'     => $message,
            'status_code' => $statusCode,
            'data'        => $items,
            'pagination'  => [
                'per_page'    => $perPage,
                'next_cursor' => $nextCursor,
                'prev_cursor' => $prevCursor,
                'has_more'    => $nextCursor !== null,
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Create a validation error response.
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): array
    {
        return self::error(
            message: $message,
            statusCode: 422,
            errors: $errors
        );
    }

    /**
     * Create an unauthorized response.
     */
    public static function unauthorized(string $message = 'Unauthorized'): array
    {
        return self::error(
            message: $message,
            statusCode: 401
        );
    }

    /**
     * Create a forbidden response.
     */
    public static function forbidden(string $message = 'Forbidden'): array
    {
        return self::error(
            message: $message,
            statusCode: 403
        );
    }

    /**
     * Create a not found response.
     */
    public static function notFound(string $message = 'Resource not found'): array
    {
        return self::error(
            message: $message,
            statusCode: 404
        );
    }
}
