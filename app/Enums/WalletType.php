<?php

namespace App\Enums;

enum WalletType: string
{
    case User = 'user';
    case Clearing = 'clearing';
    case Treasury = 'treasury';

    public function isSystem(): bool
    {
        return $this !== self::User;
    }
}
