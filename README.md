# Tupay Ledger & Settlement Engine

Backend assessment: double-entry ledger, step-up 2FA (EAT), FX swap with Redis locks, and HMAC settlement webhooks on **Laravel + Postgres + Redis**.

## Requirements

- PHP 8.3+
- Composer
- PostgreSQL 16+
- Redis 7+
- Extensions: `pdo_pgsql`, `bcmath`, `mbstring` (Predis is used by default  no `ext-redis` required)

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
```

Configure `.env` (see also `.env.example`):

| Variable | Purpose |
|----------|---------|
| `DB_*` | Postgres connection (`tupay` for local app) |
| `REDIS_CLIENT=predis` | Redis client |
| `CACHE_STORE=redis` | Required for swap locks + EAT single-use keys |
| `EAT_SECRET` | HMAC secret for elevated action tokens |
| `WEBHOOK_SECRET` | HMAC secret for `X-Signature` on settlement |
| `FX_NGN_CNY` / `FX_CNY_NGN` | Config FX rates (SWR fresh/stale windows via `FX_*_SECONDS`) |
| `QUEUE_CONNECTION` | Use `sync` locally to process settlement jobs inline, or `database` + `queue:work` |

```bash
createdb tupay            # once
createdb tupay_testing    # once  concurrency suite

php artisan migrate --seed
php artisan serve
```

Seeded demo user:

| Field | Value |
|-------|--------|
| Email | `test@tupay.test` |
| Password | `password` |
| TOTP secret | `JBSWY3DPEHPK3PXP` |
| Opening NGN | 50,000,000 NGN (subunits in kobo) |

Local helpers (disabled outside `local` / `testing`):

- `GET /api/dev/totp/current`  current TOTP
- `POST /api/dev/webhooks/sign`  HMAC for a webhook body

## API walkthrough

1. **Postman**  import [`docs/postman/Tupay Assessment.postman_collection.json`](docs/postman/Tupay%20Assessment.postman_collection.json). Collection variables include `base_url` (`http://127.0.0.1:8000`), credentials, wallets, `token`, `totp`, `elevated_action_token`, and `x-signature`. Fill wallet IDs from **Get user wallets**, TOTP from the dev endpoint, then challenge → swap → sign → settlement (reuse the **exact** JSON body for sign + settle).
2. **HTTP file**  open [`api-test.http`](api-test.http) in VS Code with the **REST Client** extension (`humao.rest-client`). With `php artisan serve` running, click **Send Request** above each block (top to bottom). After **Wallets**, paste NGN/CNY ids into the `@sourceWalletId` / `@destinationWalletId` / `@walletId` variables.

Suggested order: Login → TOTP → Wallets → 2FA challenge → Swap → Ledger → Sign webhook → Settlement.

## Architecture (short)

- **Money**  integer subunits (`BIGINT`); BCMath + banker’s rounding in `App\Support\MoneyMath`
- **Ledger**  append-only `ledger_entries`; balances via `wallet_balances` view; deferred non-negative trigger on user/treasury wallets
- **EAT**  TOTP challenge issues a 60s HMAC token bound to action + payload hash; Redis `GETDEL` enforces single use (failures → **401**)
- **Swap**  ordered Redis locks, slippage rules, double-entry legs including fee to treasury when applicable
- **Settlement**  verify `X-Signature`, persist idempotent `webhook_events`, queue job, status state machine

## Quality gates

```bash
composer lint              # Pint --test
composer lint:fix         # Pint autofix
composer analyse           # PHPStan level 8 (Larastan)
composer test              # PHPUnit default suite (sqlite; excludes concurrency)
composer test:concurrency  # Parallel swap race (Postgres + Redis)
composer qa                # lint + analyse + both test suites
```

PHPStan config: [`phpstan.neon`](phpstan.neon) (level **8**).

The default `phpunit.xml` suite excludes `tests/Feature/Concurrency` (Postgres-specific). Run that suite only via `phpunit.concurrency.xml`.

### Concurrency race test

Proves ten parallel swaps against a single funded balance: **exactly one** `200`, nine `409`/`422`, source balance never overdrafts.

```bash
# Ensure tupay_testing exists and Redis is up; credentials in phpunit.concurrency.xml
# (DB password defaults to 1, override via env if needed, e.g. DB_PASSWORD=postgres)
php artisan test -c phpunit.concurrency.xml
```

### CI

GitHub Actions: [`.github/workflows/ci.yml`](.github/workflows/ci.yml) runs Pint, PHPStan 8, default tests, and the concurrency suite against Postgres + Redis services.

## Main endpoints

| Method | Path | Notes |
|--------|------|--------|
| `POST` | `/api/login` | Sanctum token |
| `POST` | `/api/2fa/challenge` | TOTP → EAT |
| `POST` | `/api/swap` | Bearer + `X-Elevated-Action-Token` |
| `GET` | `/api/wallets` | Balances (subunits) |
| `GET` | `/api/ledger/{wallet_id}` | Paginated entries |
| `POST` | `/api/webhooks/settlement` | `X-Signature` HMAC |
