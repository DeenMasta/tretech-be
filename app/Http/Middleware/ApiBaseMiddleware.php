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

        $response->header('Content-Type', 'application/json');
        $response->header('X-API-Version', '1.0');
        $response->header('X-Request-ID', $request->header('X-Request-ID') ?? $this->generateRequestId());

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
