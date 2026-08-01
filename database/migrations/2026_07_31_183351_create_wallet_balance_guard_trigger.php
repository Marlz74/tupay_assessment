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
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_negative_wallet_balance()
            RETURNS TRIGGER
            LANGUAGE plpgsql
            AS $$
            DECLARE
                v_type text;
                v_balance bigint;
            BEGIN
                SELECT type INTO v_type
                FROM wallets
                WHERE id = NEW.wallet_id;
                IF v_type = 'clearing' THEN
                    RETURN NULL;
                END IF;
                SELECT COALESCE(SUM(amount), 0) INTO v_balance
                FROM ledger_entries
                WHERE wallet_id = NEW.wallet_id;
                IF v_balance < 0 THEN
                    RAISE EXCEPTION 'wallet_balance_negative: wallet_id=% balance=%',
                        NEW.wallet_id, v_balance
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NULL;
            END;
            $$;
        ");
        DB::statement('
            CREATE CONSTRAINT TRIGGER trg_prevent_negative_wallet_balance
            AFTER INSERT ON ledger_entries
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW
            EXECUTE FUNCTION prevent_negative_wallet_balance()
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_prevent_negative_wallet_balance ON ledger_entries');
        DB::statement('DROP FUNCTION IF EXISTS prevent_negative_wallet_balance()');
    }
};
