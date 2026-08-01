<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesLedgerFixture;
use Tests\TestCase;

class SettlementWebhookTest extends TestCase
{
    use CreatesLedgerFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCurrenciesAndSystemWallets();
        $this->seedUserWithWallets(0);
        config(['tupay.webhook_secret' => 'testing-webhook-secret']);
    }

    public function test_missing_signature_returns_401(): void
    {
        $this->postJson('/api/webhooks/settlement', $this->settlementPayload('set_missing_sig'))
            ->assertStatus(401);
    }

    public function test_invalid_signature_returns_401(): void
    {
        $payload = $this->settlementPayload('set_bad_sig');
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call(
            'POST',
            '/api/webhooks/settlement',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SIGNATURE' => 'deadbeef',
            ],
            $raw,
        )->assertStatus(401);
    }

    public function test_valid_settlement_credits_wallet(): void
    {
        $payload = $this->settlementPayload('set_ok_001', 50_000);
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, 'testing-webhook-secret');

        $this->call(
            'POST',
            '/api/webhooks/settlement',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_SIGNATURE' => $signature,
            ],
            $raw,
        )->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.duplicate', false);

        $this->assertDatabaseHas('webhook_events', [
            'provider_reference' => 'set_ok_001',
        ]);

        $credited = (int) LedgerEntry::query()
            ->where('wallet_id', $this->ngnWallet->id)
            ->where('reference_type', 'settlement')
            ->sum('amount');

        $this->assertSame(50_000, $credited);
    }

    public function test_duplicate_provider_reference_is_idempotent(): void
    {
        $payload = $this->settlementPayload('set_dup_001', 25_000);
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $raw, 'testing-webhook-secret');

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
        ];

        $this->call('POST', '/api/webhooks/settlement', [], [], [], $headers, $raw)
            ->assertOk()
            ->assertJsonPath('data.duplicate', false);

        $this->call('POST', '/api/webhooks/settlement', [], [], [], $headers, $raw)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $this->assertSame(1, WebhookEvent::query()->where('provider_reference', 'set_dup_001')->count());

        $credited = (int) LedgerEntry::query()
            ->where('wallet_id', $this->ngnWallet->id)
            ->where('reference_type', 'settlement')
            ->sum('amount');

        $this->assertSame(25_000, $credited);
    }

    /** @return array{provider_reference: string, status: string, wallet_id: string, amount: int, currency_code: string} */
    private function settlementPayload(string $reference, int $amount = 50_000): array
    {
        return [
            'provider_reference' => $reference,
            'status' => 'completed',
            'wallet_id' => $this->ngnWallet->id,
            'amount' => $amount,
            'currency_code' => 'NGN',
        ];
    }
}
