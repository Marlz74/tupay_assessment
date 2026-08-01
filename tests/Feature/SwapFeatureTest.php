<?php

namespace Tests\Feature;

use App\Services\Security\StepUpTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\CreatesLedgerFixture;
use Tests\TestCase;

class SwapFeatureTest extends TestCase
{
    use CreatesLedgerFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireRedis();
        $this->seedCurrenciesAndSystemWallets();
    }

    public function test_swap_succeeds_with_valid_eat(): void
    {
        $token = $this->seedUserWithWallets(1_000_000);
        $body = $this->swapPayload(10_000);
        $eat = $this->app->make(StepUpTokenService::class)
            ->issue($this->fixtureUser->id, 'swap', $body);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Elevated-Action-Token' => $eat,
        ])->postJson('/api/swap', $body);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.source_amount', 10_000)
            ->assertJsonPath('data.source_currency', 'NGN')
            ->assertJsonPath('data.destination_currency', 'CNY');

        $this->assertNotEmpty($response->json('data.transaction_id'));
    }

    public function test_swap_rejects_insufficient_balance(): void
    {
        $token = $this->seedUserWithWallets(5_000);
        $body = $this->swapPayload(50_000);
        $eat = $this->app->make(StepUpTokenService::class)
            ->issue($this->fixtureUser->id, 'swap', $body);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Elevated-Action-Token' => $eat,
        ])->postJson('/api/swap', $body)
            ->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_two_factor_challenge_issues_eat_with_valid_totp(): void
    {
        $token = $this->seedUserWithWallets();
        $body = $this->swapPayload();
        $totp = app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP');

        $this->withToken($token)
            ->postJson('/api/2fa/challenge', [
                'totp' => $totp,
                'action' => 'swap',
                'action_payload' => $body,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['elevated_action_token', 'expires_in']]);
    }
}
