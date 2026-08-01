<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case SwapDebit = 'swap_debit';
    case SwapCredit = 'swap_credit';
    case SwapClearingCredit = 'swap_clearing_credit';
    case SwapClearingDebit = 'swap_clearing_debit';
    case SwapFee = 'swap_fee';

    // To give test user balance
    case SeedDebit = 'seed_debit';
    case SeedCredit = 'seed_credit';

    case SettlementDebit = 'settlement_debit';
    case SettlementCredit = 'settlement_credit';
}
