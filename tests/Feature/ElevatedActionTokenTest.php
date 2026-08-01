<?php

namespace Tests\Feature;

use App\Services\Security\StepUpTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesLedgerFixture;
use Tests\TestCase;

class ElevatedActionTokenTest extends TestCase
{
    use CreatesLedgerFixture;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireRedis();
        $this->seedCurrenciesAndSystemWallets();
    }

    public function test_missing_eat_returns_401(): void
    {
        $token = $this->seedUserWithWallets();
        $body = $this->swapPayload();

        $this->withToken($token)
            ->postJson('/api/swap', $body)
            ->assertStatus(401)
            ->assertJsonPath('status', 'error');
    }

    public function test_replaying_eat_returns_401(): void
    {
        $token = $this->seedUserWithWallets(1_000_000);
        $body = $this->swapPayload(10_000);
        $eat = $this->app->make(StepUpTokenService::class)
            ->issue($this->fixtureUser->id, 'swap', $body);

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'X-Elevated-Action-Token' => $eat,
        ];

        $this->withHeaders($headers)->postJson('/api/swap', $body)->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/swap', $body)
            ->assertStatus(401)
            ->assertJsonPath('status', 'error');
    }

    public function test_eat_bound_to_different_payload_returns_401(): void
    {
        $token = $this->seedUserWithWallets(1_000_000);
        $body = $this->swapPayload(10_000);
        $eat = $this->app->make(StepUpTokenService::class)
            ->issue($this->fixtureUser->id, 'swap', $body);

        $tampered = $body;
        $tampered['amount'] = 20_000;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Elevated-Action-Token' => $eat,
        ])->postJson('/api/swap', $tampered)
            ->assertStatus(401);
    }
}
