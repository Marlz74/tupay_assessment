<?php

namespace Tests\Feature\Concurrency;

use App\DTO\Ledger\LedgerLegDTO;
use App\Enums\LedgerEntryType;
use App\Enums\WalletType;
use App\Models\Currency;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\PostingService;
use App\Services\Security\StepUpTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SwapRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = [];

    private const SWAP_AMOUNT = 1_000_000; // kobo; below slippage threshold → fee 0

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Concurrency race test requires Postgres (use phpunit.concurrency.xml).');
        }

        if (config('cache.default') !== 'redis') {
            $this->markTestSkipped('Concurrency race test requires CACHE_STORE=redis.');
        }

        try {
            Redis::connection()->ping();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis unavailable: '.$e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->app !== null && config('database.default') === 'pgsql') {
            $this->artisan('migrate:fresh');
            RefreshDatabaseState::$migrated = false;
        }

        parent::tearDown();
    }

    public function test_ten_concurrent_swaps_only_one_succeeds_without_overdraft(): void
    {
        [$user, $source, $destination, $token] = $this->seedRaceFixture();

        $body = [
            'source_wallet_id' => $source->id,
            'destination_wallet_id' => $destination->id,
            'amount' => self::SWAP_AMOUNT,
        ];

        /** @var StepUpTokenService $stepUp */
        $stepUp = $this->app->make(StepUpTokenService::class);

        $eats = [];
        for ($i = 0; $i < 10; $i++) {
            $eats[] = $stepUp->issue($user->id, 'swap', $body);
        }

        $worker = base_path('tests/concurrency/swap_race_worker.php');
        $workerEnv = $this->workerEnvironment();
        $processes = [];

        foreach ($eats as $eat) {
            $payload = json_encode([
                'token' => $token,
                'eat' => $eat,
                'body' => $body,
            ], JSON_THROW_ON_ERROR);

            $process = new Process(
                [PHP_BINARY, $worker, $payload],
                base_path(),
                $workerEnv,
                null,
                60,
            );

            $process->start();
            $processes[] = $process;
        }

        $statuses = [];
        foreach ($processes as $process) {
            $process->wait();
            $statuses[] = (int) trim($process->getOutput());
            if ($process->getOutput() === '' && $process->getErrorOutput() !== '') {
                $this->fail('Worker failed: '.$process->getErrorOutput());
            }
        }

        $ok = count(array_filter($statuses, static fn (int $s): bool => $s === 200));
        $conflicts = count(array_filter(
            $statuses,
            static fn (int $s): bool => in_array($s, [409, 422], true),
        ));

        $this->assertSame(1, $ok, 'Expected exactly one 200 OK. Statuses: '.implode(',', $statuses));
        $this->assertSame(9, $conflicts, 'Expected nine 409/422. Statuses: '.implode(',', $statuses));

        $balance = (int) LedgerEntry::query()
            ->where('wallet_id', $source->id)
            ->sum('amount');

        $this->assertSame(0, $balance, 'Source wallet must not overdraft; exact one swap worth of funds.');
        $this->assertGreaterThanOrEqual(0, $balance);
    }

    private function workerEnvironment(): array
    {
        $inherited = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && preg_match('/^[A-Z_][A-Z0-9_]*$/', $key) === 1) {
                $inherited[$key] = $value;
            }
        }

        return array_merge($inherited, [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) config('database.connections.pgsql.host'),
            'DB_PORT' => (string) config('database.connections.pgsql.port'),
            'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
            'DB_USERNAME' => (string) config('database.connections.pgsql.username'),
            'DB_PASSWORD' => (string) config('database.connections.pgsql.password'),
            'CACHE_STORE' => 'redis',
            'REDIS_CLIENT' => (string) config('database.redis.client', 'predis'),
            'REDIS_HOST' => (string) config('database.redis.default.host', '127.0.0.1'),
            'REDIS_PORT' => (string) config('database.redis.default.port', '6379'),
            'EAT_SECRET' => (string) config('app.eat_secret'),
            'QUEUE_CONNECTION' => 'sync',
        ]);
    }

    /**
     * @return array{0: User, 1: Wallet, 2: Wallet, 3: string}
     */
    private function seedRaceFixture(): array
    {
        $ngn = Currency::create(['code' => 'NGN', 'logo' => null]);
        $cny = Currency::create(['code' => 'CNY', 'logo' => null]);

        foreach (
            [
                [WalletType::Clearing, $ngn],
                [WalletType::Clearing, $cny],
                [WalletType::Treasury, $ngn],
            ] as [$type, $currency]
        ) {
            Wallet::create([
                'user_id' => null,
                'currency_id' => $currency->id,
                'type' => $type,
                'slug' => Wallet::slugForSystem($type, $currency->code),
            ]);
        }

        $user = User::create([
            'firstname' => 'Race',
            'lastname' => 'User',
            'email' => 'race@tupay.test',
            'password' => Hash::make('password'),
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'totp_confirmed_at' => now(),
            'email_verified_at' => now(),
        ]);

        $source = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => $ngn->id,
            'type' => WalletType::User,
            'slug' => Wallet::slugForUser($user->id, 'NGN'),
        ]);

        $destination = Wallet::create([
            'user_id' => $user->id,
            'currency_id' => $cny->id,
            'type' => WalletType::User,
            'slug' => Wallet::slugForUser($user->id, 'CNY'),
        ]);

        $clearingNgn = Wallet::query()
            ->where('slug', Wallet::slugForSystem(WalletType::Clearing, 'NGN'))
            ->firstOrFail();

        // Exactly one swap worth of balance (fee 0 under threshold)
        $this->app->make(PostingService::class)->post(
            legs: [
                new LedgerLegDTO(
                    walletId: $clearingNgn->id,
                    currencyId: $ngn->id,
                    amount: -self::SWAP_AMOUNT,
                    entryType: LedgerEntryType::SeedDebit,
                    description: 'Race fixture mint',
                ),
                new LedgerLegDTO(
                    walletId: $source->id,
                    currencyId: $ngn->id,
                    amount: self::SWAP_AMOUNT,
                    entryType: LedgerEntryType::SeedCredit,
                    description: 'Race fixture mint',
                ),
            ],
            wallets: [$clearingNgn, $source],
        );

        $token = $user->createToken('race')->plainTextToken;

        return [$user, $source, $destination, $token];
    }
}
