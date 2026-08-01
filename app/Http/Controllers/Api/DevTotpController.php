<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DevTotpController extends Controller
{
    public function current(Request $request, Google2FA $google2fa): JsonResponse
    {
        if (! App::environment(['local', 'testing'])) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $secret = $user->totp_secret;

        if ($secret === null || $secret === '') {
            return ApiResponse::error('TOTP is not configured for this user.', 422);
        }

        return ApiResponse::success(
            message: 'Dev-only current TOTP code',
            data: [
                'totp' => $google2fa->getCurrentOtp($secret),
                'expires_in_hint_seconds' => 30,
                'warning' => 'Local/testing only. Do not enable in production.',
            ],
        );
    }
}
