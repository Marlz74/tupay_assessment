<?php

namespace App\Enums;

enum SettlementStatus: string
{
    case Initiated = 'initiated';
    case Completed = 'completed';
    case Failed = 'failed';
}
