<?php

namespace App\Services\Settlement;

use App\Enums\SettlementStatus;

final class SettlementStateMachineService
{
    public function canTransition(?SettlementStatus $currentStatus, SettlementStatus $incomingStatus): bool
    {
        if ($currentStatus === null) {
            return true;
        }

        if ($currentStatus === $incomingStatus) {
            return true;
        }

        return match ($currentStatus) {
            SettlementStatus::Initiated => in_array($incomingStatus, [
                SettlementStatus::Completed,
                SettlementStatus::Failed,
            ], true),
            SettlementStatus::Completed, SettlementStatus::Failed => false,
        };
    }

    /**
     * Credit ledger only when status is completed.
     */
    public function shouldCreditLedger(?SettlementStatus $current, SettlementStatus $incoming): bool
    {
        if ($incoming !== SettlementStatus::Completed) {
            return false;
        }

        return $current === null || $current === SettlementStatus::Initiated;
    }
}
