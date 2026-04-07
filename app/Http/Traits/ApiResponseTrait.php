<?php

namespace App\Http\Traits;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Return a success response.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $statusCode = 200,
        array $additional = []
    ): JsonResponse {
        return response()->json(
            ApiResponse::success($data, $message, $statusCode, $additional),
            $statusCode
        );
    }

    /**
     * Return an error response.
     */
    protected function errorResponse(
        string $message = 'An error occurred',
        int $statusCode = 400,
        array $errors = [],
        array $additional = []
    ): JsonResponse {
        return response()->json(
            ApiResponse::error($message, $statusCode, $errors, $additional),
            $statusCode
        );
    }

    /**
     * Return a paginated response.
     */
    protected function paginatedResponse(
        mixed $items,
        int $total,
        int $perPage,
        int $currentPage,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json(
            ApiResponse::paginated($items, $total, $perPage, $currentPage, $message, $statusCode),
            $statusCode
        );
    }

    /**
     * Return a cursor-paginated response.
     * Use for large tables (audit_logs, lot_movements) where a COUNT(*) would be expensive.
     *
     * @param \Illuminate\Contracts\Pagination\CursorPaginator $paginator
     */
    protected function cursorPaginatedResponse(
        mixed $items,
        \Illuminate\Contracts\Pagination\CursorPaginator $paginator,
        string $message = 'Success',
        int $statusCode = 200
    ): JsonResponse {
        return response()->json(
            ApiResponse::cursorPaginated(
                items:      $items,
                perPage:    $paginator->perPage(),
                nextCursor: $paginator->nextCursor()?->encode(),
                prevCursor: $paginator->previousCursor()?->encode(),
                message:    $message,
                statusCode: $statusCode,
            ),
            $statusCode
        );
    }

    /**
     * Return a validation error response.
     */
    protected function validationErrorResponse(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return response()->json(
            ApiResponse::validationError($errors, $message),
            422
        );
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return response()->json(
            ApiResponse::unauthorized($message),
            401
        );
    }

    /**
     * Return a forbidden response.
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return response()->json(
            ApiResponse::forbidden($message),
            403
        );
    }

    /**
     * Return a not found response.
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return response()->json(
            ApiResponse::notFound($message),
            404
        );
    }
}
