<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DevWebhookController extends Controller
{
    /**
     * Local/testing only: sign the raw request body with WEBHOOK_SECRET.
     * POST the exact same body bytes to /api/webhooks/settlement with X-Signature.
     */
    public function sign(Request $request): JsonResponse
    {
        if (! App::environment(['local', 'testing'])) {
            throw new NotFoundHttpException;
        }

        $secret = (string) config('tupay.webhook_secret', '');
        if ($secret === '') {
            return ApiResponse::error('WEBHOOK_SECRET is not configured.', 422);
        }

        $rawBody = $request->getContent();
        if ($rawBody === '') {
            return ApiResponse::error('Request body is required (JSON payload to sign).', 422);
        }

        $signature = hash_hmac('sha256', $rawBody, $secret);

        return ApiResponse::success(
            message: 'Dev-only webhook signature',
            data: [
                'signature' => $signature,
                'header' => [
                    'X-Signature' => $signature,
                ],
                'body' => json_decode($rawBody, true),
                'warning' => 'Local/testing only. POST this exact body to /api/webhooks/settlement.',
            ],
        );
    }
}
