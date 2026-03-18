<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*') || $e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            $message = match ($status) {
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not found',
                405 => 'Method not allowed',
                429 => 'Too many requests',
                default => 'Internal server error',
            };

            return response()->json(['error' => $message], $status);
        });
    })->create();
