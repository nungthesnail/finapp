<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        $exceptions->report(function (\Throwable $e, Request $request): void {
            if (!$request->is('api/*')) {
                return;
            }
            $sessionUid = $request->hasSession() ? $request->session()->get('uid') : null;

            $payload = $request->except([
                'password',
                'password_confirmation',
                'token',
                'access_token',
                'refresh_token',
                'secret',
                'api_key',
            ]);

            Log::error('API exception captured', [
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request' => [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'session_uid' => $sessionUid,
                    'payload' => $payload,
                ],
            ]);
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (!$request->is('api/*') || $e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $level = $status >= 500 ? 'error' : 'warning';
            $sessionUid = $request->hasSession() ? $request->session()->get('uid') : null;
            Log::log($level, 'API exception response', [
                'exception_class' => $e::class,
                'message' => $e->getMessage(),
                'status' => $status,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request' => [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                    'session_uid' => $sessionUid,
                ],
            ]);

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
