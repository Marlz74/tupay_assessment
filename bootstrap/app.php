<?php

use App\Exceptions\ConcurrentSwapConflictException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidElevatedTokenException;
use App\Exceptions\InvalidTotpException;
use App\Http\Middleware\VerifyElevatedActionToken;
use App\Http\Middleware\VerifyWebhookSignature;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'eat' => VerifyElevatedActionToken::class,
            'webhook.verify' => VerifyWebhookSignature::class,

        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error(
                    message: $e->getMessage(),
                    status: 422,
                    errors: $e->errors(),
                );
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error(
                    message: 'Unauthenticated.',
                    status: 401,
                );
            }

            if ($e instanceof InvalidElevatedTokenException) {
                return ApiResponse::error($e->getMessage(), 401);
            }

            if ($e instanceof InvalidTotpException) {
                return ApiResponse::error($e->getMessage(), 422);
            }

            if ($e instanceof InsufficientBalanceException) {
                return ApiResponse::error($e->getMessage(), 422);
            }

            if ($e instanceof ConcurrentSwapConflictException) {
                return ApiResponse::error($e->getMessage(), 409);
            }

            if ($e instanceof AuthorizationException) {
                return ApiResponse::error($e->getMessage() ?: 'Forbidden.', 403);
            }

            if ($e instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    message: $e->getMessage() !== '' ? $e->getMessage() : 'Request failed',
                    status: $e->getStatusCode(),
                );
            }

            return ApiResponse::fromException($e, 500);
        });
    })->create();
