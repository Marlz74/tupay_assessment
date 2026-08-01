<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id')->index();
            $table->foreignUuid('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();
            $table->foreignUuid('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();
            $table->bigInteger('amount');
            $table->string('entry_type', 64)->nullable();
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['wallet_id', 'created_at', 'id']);
            $table->index(['reference_type', 'reference_id']);

        });
        DB::statement('
            ALTER TABLE ledger_entries
            ADD CONSTRAINT ledger_entries_amount_nonzero_check
            CHECK (amount <> 0)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
