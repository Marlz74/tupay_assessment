<?php

namespace App\Http\Middleware;

use App\Exceptions\InvalidElevatedTokenException;
use App\Services\Security\StepUpTokenService;
use App\Support\SwapActionPayload;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyElevatedActionToken
{
    public function __construct(
        private readonly StepUpTokenService $stepUpTokenService,
    ) {}

    public function handle(Request $request, Closure $next, string $action = 'swap'): Response
    {
        $token = $request->header('X-Elevated-Action-Token');

        if ($token === null || $token === '') {
            throw new InvalidElevatedTokenException('Elevated action token is required.');
        }

        $user = $request->user();
        if ($user === null) {
            throw new InvalidElevatedTokenException('Unauthenticated.');
        }

        $payload = SwapActionPayload::extract($request->all());

        $this->stepUpTokenService->consume(
            token: $token,
            userId: $user->id,
            action: $action,
            actionPayload: $payload,
        );

        return $next($request);
    }
}
