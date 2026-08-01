<?php

namespace App\Services\Security;

use App\Exceptions\InvalidElevatedTokenException;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Log;

final class StepUpTokenService
{
    private const TTL_SECONDS = 60;

    private const REDIS_PREFIX = 'eat:';

    public function __construct(
        private readonly HashActionPayloadService $hashActionService,
    ) {}

    /** @param  array<string, mixed>  $actionPayload */
    public function issue(string $userId, string $action, array $actionPayload): string
    {
        $jti = (string) Str::uuid();
        $hashActionPayload = $this->hashActionService->hash($actionPayload);
        $exp = time() + self::TTL_SECONDS;

        $payload = [
            'jti' => $jti,
            'sub' => $userId,
            'action' => $action,
            'action_hash' => $hashActionPayload,
            'iat' => time(),
            'exp' => $exp,
        ];

        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $payloadB64 = $this->b64urlEncode($encodedPayload);
        $sigB64 = $this->b64urlEncode($this->sign($payloadB64));

        Redis::setex(self::REDIS_PREFIX.$jti, self::TTL_SECONDS, $hashActionPayload);

        return $payloadB64.'.'.$sigB64;
    }

    /** @param  array<string, mixed>  $actionPayload */
    public function consume(
        string $token,
        string $userId,
        string $action,
        array $actionPayload,
    ): void {
        [$payloadB64, $sigB64] = $this->split($token);

        $expectedSig = $this->b64urlEncode($this->sign($payloadB64));
        if (! hash_equals($expectedSig, $sigB64)) {
            Log::warning('Invalid elevated action token signature.', [
                'user_id' => $userId,
                'action' => $action,
            ]);
            throw new InvalidElevatedTokenException('The action authorization could not be verified.');
        }

        $payload = json_decode($this->b64urlDecode($payloadB64), true, 512, JSON_THROW_ON_ERROR);

        if (($payload['exp'] ?? 0) < time()) {
            throw new InvalidElevatedTokenException('The action authorization has expired.');
        }

        if (($payload['sub'] ?? '') !== $userId) {
            Log::warning('Elevated action token user mismatch.', [
                'user_id' => $userId,
                'action' => $action,
            ]);

            // Security audit
            throw new InvalidElevatedTokenException('The action authorization could not be verified.');
        }

        if (($payload['action'] ?? '') !== $action) {

            Log::warning('Elevated action token action mismatch.', [
                'user_id' => $userId,
                'action' => $action,
            ]);

            // Security audit

            throw new InvalidElevatedTokenException('The action authorization could not be verified.');
        }

        $requestHash = $this->hashActionService->hash($actionPayload);
        if (! hash_equals($payload['action_hash'], $requestHash)) {

            Log::warning('Elevated action token action payload mismatch.', [
                'user_id' => $userId,
                'action' => $action,
            ]);

            // Security audit
            throw new InvalidElevatedTokenException('The action authorization could not be verified.');
        }

        $jti = $payload['jti'] ?? '';
        $stored = Redis::command('GETDEL', [self::REDIS_PREFIX.$jti]);

        if ($stored === null || $stored === false) {
            throw new InvalidElevatedTokenException('Elevated action token already used or revoked.');
        }

        if (! hash_equals((string) $stored, $requestHash)) {
            throw new InvalidElevatedTokenException('The action authorization could not be verified.');
        }
    }

    public function ttlSeconds(): int
    {
        return self::TTL_SECONDS;
    }

    private function sign(string $payloadB64): string
    {
        return hash_hmac('sha256', $payloadB64, (string) config('app.eat_secret'), true);
    }

    /** @return array{0: string, 1: string} */
    private function split(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new InvalidElevatedTokenException('The action authorization could not be verified.');
        }

        return [$parts[0], $parts[1]];
    }

    private function b64urlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private function b64urlDecode(string $b64): string
    {
        $remainder = strlen($b64) % 4;
        if ($remainder > 0) {
            $b64 .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidElevatedTokenException('The action authorization could not be verified.');
        }

        return $decoded;
    }
}
