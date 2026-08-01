<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerEntryResource;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {}

    public function index(Request $request, string $wallet_id): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        $paginator = $this->ledgerService->listWalletLedgers(
            user: $user,
            walletId: $wallet_id,
            perPage: (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            message: 'Ledger entries retrieved',
            data: LedgerEntryResource::collection($paginator->getCollection())->collection,
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
