<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // API middleware aliases
        $middleware->alias([
            'api.base' => \App\Http\Middleware\ApiBaseMiddleware::class,
            'api.log' => \App\Http\Middleware\LogApiRequests::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'all-permissions' => \App\Http\Middleware\CheckAllPermissions::class,
        ]);

        // Apply API base middleware to all API routes
        $middleware->group('api', [
            \App\Http\Middleware\ApiBaseMiddleware::class,
            \App\Http\Middleware\LogApiRequests::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return app(\App\Exceptions\Handler::class)->render($request, $e);
            }
        });
    })->create();
