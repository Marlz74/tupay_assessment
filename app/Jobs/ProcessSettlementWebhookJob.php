<?php

namespace App\Jobs;

use App\DTO\Ledger\LedgerLegDTO;
use App\Enums\LedgerEntryType;
use App\Enums\SettlementStatus;
use App\Enums\WalletType;
use App\Models\Currency;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use App\Models\WebhookEvent;
use App\Services\Ledger\PostingService;
use App\Services\Settlement\SettlementStateMachineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProcessSettlementWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $webhookEventId,
    ) {}

    public function handle(
        SettlementStateMachineService $stateMachineService,
        PostingService $postingService,
    ): void {
        DB::transaction(function () use ($stateMachineService, $postingService): void {
            $event = WebhookEvent::whereKey($this->webhookEventId)
                ->lockForUpdate()
                ->first();

            if ($event === null) {
                return;
            }

            $payload = $event->payload;
            $incomingStatus = SettlementStatus::from((string) $payload['status']);

            $previousStatus = $event->status;

            if (
                ! $stateMachineService->canTransition($previousStatus, $incomingStatus)
                && ! ($previousStatus === $incomingStatus)
            ) {
                // operation is not allowed
                if ($previousStatus !== $incomingStatus) {
                    Log::info('Settlement transition ignored', [
                        'provider_reference' => $event->provider_reference,
                        'current' => $previousStatus->value,
                        'incoming' => $incomingStatus->value,
                    ]);

                    return;
                }
            }

            // If settlement is already completed
            if ($previousStatus === SettlementStatus::Completed && $incomingStatus !== SettlementStatus::Completed) {
                return;
            }

            // If settlement is already failed
            if ($previousStatus === SettlementStatus::Failed && $incomingStatus !== SettlementStatus::Failed) {
                return;
            }

            $alreadyCredited = LedgerEntry::where('reference_type', 'settlement')
                ->where('reference_id', $event->id)
                ->exists();

            if ($incomingStatus === SettlementStatus::Completed && ! $alreadyCredited) {
                $this->creditWallet($postingService, $event, $payload);
                $event->processed_at = now();
            }

            $event->status = $incomingStatus;
            if ($incomingStatus === SettlementStatus::Failed) {
                $event->processed_at ??= now();
            }
            $event->save();
        });
    }

    /** @param  array<string, mixed>  $payload */
    private function creditWallet(PostingService $postingService, WebhookEvent $event, array $payload): void
    {
        $walletId = (string) $payload['wallet_id'];
        $amount = (int) $payload['amount'];
        $currencyCode = strtoupper((string) $payload['currency_code']);

        $userWallet = Wallet::with('currency')->whereKey($walletId)->lockForUpdate()->firstOrFail();

        if ($userWallet->type !== WalletType::User) {
            throw new RuntimeException('Settlement wallet must be a user wallet.');
        }

        $userWalletCurrency = $userWallet->currency;

        if ($userWalletCurrency === null || $userWalletCurrency->code !== $currencyCode) {
            throw new RuntimeException('Settlement currency does not match wallet.');
        }

        $alreadyPosted = LedgerEntry::where('reference_type', 'settlement')
            ->where('reference_id', $event->id)
            ->exists();

        if ($alreadyPosted) {
            return;
        }

        $currency = Currency::where('code', $currencyCode)->firstOrFail();
        $clearingWallet = Wallet::where('slug', Wallet::slugForSystem(WalletType::Clearing, $currencyCode))
            ->lockForUpdate()
            ->firstOrFail();

        $postingService->post(
            legs: [
                new LedgerLegDTO(
                    walletId: $clearingWallet->id,
                    currencyId: $currency->id,
                    amount: -$amount,
                    entryType: LedgerEntryType::SettlementDebit,
                    description: 'Settlement clearing debit',
                    referenceType: 'settlement',
                    referenceId: $event->id,
                ),
                new LedgerLegDTO(
                    walletId: $userWallet->id,
                    currencyId: $currency->id,
                    amount: $amount,
                    entryType: LedgerEntryType::SettlementCredit,
                    description: 'Settlement credit',
                    referenceType: 'settlement',
                    referenceId: $event->id,
                ),
            ],
            wallets: [$clearingWallet, $userWallet],
        );
    }
}
