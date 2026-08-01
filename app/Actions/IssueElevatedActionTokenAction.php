<?php

namespace App\Actions;

use App\Models\User;
use App\Services\Security\StepUpTokenService;
use App\Services\Security\VerifyTotpService;
use App\Support\SwapActionPayload;

final class IssueElevatedActionTokenAction
{
    public function __construct(
        private readonly VerifyTotpService $verifyTotpService,
        private readonly StepUpTokenService $stepUpTokenService,
    ) {}

    /**
     * @param  array<string, mixed>  $actionPayload
     * @return array{elevated_action_token: string, expires_in: int}
     */
    public function execute(User $user, string $totp, string $action, array $actionPayload): array
    {
        $this->verifyTotpService->verify($user, $totp);

        $payload = SwapActionPayload::extract($actionPayload);

        $token = $this->stepUpTokenService->issue($user->id, $action, $payload);

        return [
            'elevated_action_token' => $token,
            'expires_in' => $this->stepUpTokenService->ttlSeconds(),
        ];
    }
}
