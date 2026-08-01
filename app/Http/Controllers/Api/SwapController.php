<?php

namespace App\Http\Controllers\Api;

use App\Actions\ExecuteSwapAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Swap\SwapRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SwapController extends Controller
{
    public function store(SwapRequest $request, ExecuteSwapAction $action): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $data = $action->execute(
            user: $user,
            sourceWalletId: $request->validated('source_wallet_id'),
            destinationWalletId: $request->validated('destination_wallet_id'),
            amount: (int) $request->validated('amount'),
        );

        return ApiResponse::success(
            message: 'Swap completed',
            data: $data,
        );
    }
}
