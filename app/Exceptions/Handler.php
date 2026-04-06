<?php

namespace App\Exceptions;

use App\Services\Audit\ErrorLogService;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (\Throwable $e) {
            // Skip logging for expected/handled exception types
            $skip = [
                ApiException::class,
                LaravelValidationException::class,
                AuthenticationException::class,
                AuthorizationException::class,
                ModelNotFoundException::class,
                NotFoundHttpException::class,
            ];

            foreach ($skip as $class) {
                if ($e instanceof $class) {
                    return false;
                }
            }

            // Persist unexpected errors to error_logs table
            try {
                /** @var ErrorLogService $errorLogService */
                $errorLogService = app(ErrorLogService::class);
                $errorLogService->logException($e, source: 'app', extraContext: [
                    'url'    => request()?->fullUrl(),
                    'method' => request()?->method(),
                    'user'   => request()?->user()?->id,
                ]);
            } catch (\Throwable) {
                // Never throw from inside reportable — silently skip if DB is unavailable
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, \Throwable $exception): Response
    {
        // Handle API exceptions
        if ($exception instanceof ApiException) {
            return $exception->render();
        }

        // Handle Laravel validation exceptions
        if ($exception instanceof LaravelValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'status_code' => 422,
                'errors' => $exception->errors(),
                'timestamp' => now()->toIso8601String(),
            ], 422);
        }

        // Handle authentication exceptions
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login to continue',
                'status_code' => 401,
                'errors' => [],
                'timestamp' => now()->toIso8601String(),
            ], 401);
        }

        // Handle authorization exceptions
        if ($exception instanceof AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to perform this action',
                'status_code' => 403,
                'errors' => [],
                'timestamp' => now()->toIso8601String(),
            ], 403);
        }

        // Handle model not found exceptions
        if ($exception instanceof ModelNotFoundException) {
            $modelClass = $exception->getModel();
            $modelName = class_basename($modelClass);

            return response()->json([
                'success' => false,
                'message' => "$modelName not found",
                'status_code' => 404,
                'errors' => [],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        // Handle HTTP not found exceptions
        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found',
                'status_code' => 404,
                'errors' => [],
                'timestamp' => now()->toIso8601String(),
            ], 404);
        }

        // Handle database exceptions
        if ($exception instanceof \Illuminate\Database\QueryException) {
            return $this->handleDatabaseException($exception);
        }

        // Handle other exceptions in production
        if (!app()->isLocal()) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request',
                'status_code' => 500,
                'errors' => [],
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }

        // In development, show detailed error
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage() ?: 'An error occurred',
            'status_code' => $exception->getCode() ?: 500,
            'errors' => [],
            'exception' => class_basename($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => array_slice($exception->getTrace(), 0, 5),
            'timestamp' => now()->toIso8601String(),
        ], $exception->getCode() ?: 500);
    }

    /**
     * Handle database exceptions.
     */
    private function handleDatabaseException(\Illuminate\Database\QueryException $exception): JsonResponse
    {
        $code = $exception->getCode();

        // Unique constraint violation
        if ($code == '23505' || str_contains($exception->getMessage(), 'Duplicate entry')) {
            return response()->json([
                'success' => false,
                'message' => 'This record already exists',
                'status_code' => 409,
                'errors' => [],
                'timestamp' => now()->toIso8601String(),
            ], 409);
        }

        // Foreign key constraint violation
        if ($code == '23503' || str_contains($exception->getMessage(), 'foreign key')) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete this record as it is referenced by other records',
                'status_code' => 409,
                'errors' => [],
                'timestamp' => now()->toIso8601String(),
            ], 409);
        }

        // In production, don't expose database details
        if (!app()->isLocal()) {
            return response()->json([
                'success' => false,
                'message' => 'A database error occurred',
                'status_code' => 500,
                'errors' => [],
                'timestamp' => now()->toIso8601String(),
            ], 500);
        }

        // In development, show the error
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'status_code' => 500,
            'errors' => [],
            'timestamp' => now()->toIso8601String(),
        ], 500);
    }
}
