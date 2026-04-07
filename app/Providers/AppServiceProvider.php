<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // 10 login attempts per minute per IP; after 5 consecutive failures
        // the same limiter key continues to block. Using IP so credential-stuffing
        // from a single address is throttled regardless of which email is tried.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinutes(15, 5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'success'     => false,
                        'message'     => 'Too many login attempts. Please try again in 15 minutes.',
                        'status_code' => 429,
                        'data'        => null,
                        'timestamp'   => now()->toIso8601String(),
                    ], 429);
                });
        });
    }
}
