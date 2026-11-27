<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('code', 40)->unique();
            $table->decimal('initial_balance', 10, 2);
            $table->decimal('remaining_balance', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();

            $table->index(['is_active', 'expires_at'], 'gift_cards_active_expires_idx');
        });

        DB::statement('ALTER TABLE gift_cards ADD CONSTRAINT chk_gift_cards_initial_balance CHECK (initial_balance >= 0)');
        DB::statement('ALTER TABLE gift_cards ADD CONSTRAINT chk_gift_cards_remaining_balance CHECK (remaining_balance >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_cards');
    }
};
