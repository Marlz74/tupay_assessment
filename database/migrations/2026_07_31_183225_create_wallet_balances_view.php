<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $balanceExpr = DB::getDriverName() === 'pgsql'
            ? 'COALESCE(SUM(le.amount), 0)::bigint'
            : 'CAST(COALESCE(SUM(le.amount), 0) AS INTEGER)';

        DB::statement("
            CREATE VIEW wallet_balances AS
            SELECT
                w.id AS wallet_id,
                w.user_id,
                w.currency_id,
                w.type,
                w.slug,
                {$balanceExpr} AS balance_subunits
            FROM wallets w
            LEFT JOIN ledger_entries le ON le.wallet_id = w.id
            GROUP BY w.id, w.user_id, w.currency_id, w.type, w.slug
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS wallet_balances');
    }
};
