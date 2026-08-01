<?php

namespace App\Http\Controllers\Api;

use App\Actions\IngestSettlementWebhookAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SettlementWebhookRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class WebhookController extends Controller
{
    public function settlement(
        SettlementWebhookRequest $request,
        IngestSettlementWebhookAction $settlementAction,
    ): JsonResponse {
        $result = $settlementAction->execute($request->validated());

        return ApiResponse::success(
            message: $result['duplicate'] ? 'Webhook already processed' : 'Webhook processed',
            data: $result,
        );
    }
}
