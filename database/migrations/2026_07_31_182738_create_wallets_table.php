<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignUuid('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();
            $table->enum('type', ['user', 'clearing', 'treasury']);
            // user:{userId}:{code} | clearing:{code} | treasury:{code}
            $table->string('slug')->unique();
            $table->timestamps();
            $table->index(['type', 'currency_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE wallets
                ADD CONSTRAINT wallets_user_id_type_check
                CHECK (
                    (type = 'user' AND user_id IS NOT NULL)
                    OR (type IN ('clearing', 'treasury') AND user_id IS NULL)
                )
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
