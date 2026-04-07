<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiBaseMiddleware
{
    /**
     * Handle an incoming request.
     * Sets up common API response headers and request validation.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set API response headers
        $response = $next($request);

        // Only force JSON Content-Type on normal API responses, not file downloads
        if ($response instanceof \Illuminate\Http\Response || $response instanceof \Illuminate\Http\JsonResponse) {
            $response->header('Content-Type', 'application/json');
        }
        $response->headers->set('X-API-Version', '1.0');
        $response->headers->set('X-Request-ID', $request->header('X-Request-ID') ?? $this->generateRequestId());

        return $response;
    }

    /**
     * Generate a unique request ID for tracking.
     */
    private function generateRequestId(): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
    }
}
