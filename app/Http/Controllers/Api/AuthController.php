<?php

namespace App\Http\Controllers\Api;

use App\Actions\IssueElevatedActionTokenAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\TwoFactorChallengeRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $this->authService->login(
            email: $request->validated('email'),
            password: $request->validated('password'),
        );

        return ApiResponse::success(
            message: 'Authenticated',
            data: $data,
        );
    }

    public function twoFactorChallenge(TwoFactorChallengeRequest $request, IssueElevatedActionTokenAction $action): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $data = $action->execute(
            user: $user,
            totp: $request->validated('totp'),
            action: $request->validated('action'),
            actionPayload: $request->validated('action_payload'),
        );

        return ApiResponse::success(message: 'Elevated action token issued', data: $data);
    }
}
