<?php

namespace App\Services\Security;

use App\Exceptions\InvalidTotpException;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

final class VerifyTotpService
{
    public function __construct(
        private readonly Google2FA $google2fa = new Google2FA,
    ) {}

    public function verify(User $user, string $code): void
    {
        $secret = $user->totp_secret;

        if ($secret === null || $secret === '') {
            throw new InvalidTotpException('TOTP is not configured for this user.');
        }

        $valid = $this->google2fa->verifyKey((string) $secret, $code, 1);

        if ($valid !== true) {
            throw new InvalidTotpException('Invalid TOTP code.');
        }
    }
}
