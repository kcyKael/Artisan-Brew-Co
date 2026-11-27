<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 191)->nullable()->unique()->after('full_name');
            $table->dateTime('email_verified_at')->nullable()->after('email');
            $table->string('pending_email', 191)->nullable()->after('email_verified_at');
            $table->string('pending_email_token_hash', 64)->nullable()->after('pending_email');
            $table->dateTime('pending_email_expires_at')->nullable()->after('pending_email_token_hash');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('stock_alert_state', 20)->default('normal')->after('low_stock_threshold');
            $table->dateTime('last_stock_alert_at')->nullable()->after('stock_alert_state');
        });

        DB::statement("ALTER TABLE products ADD CONSTRAINT chk_products_stock_alert_state CHECK (stock_alert_state IN ('normal', 'low', 'sold_out'))");
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE products DROP CONSTRAINT chk_products_stock_alert_state');
        } catch (\Throwable) {
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_alert_state', 'last_stock_alert_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn([
                'email',
                'email_verified_at',
                'pending_email',
                'pending_email_token_hash',
                'pending_email_expires_at',
            ]);
        });
    }
};
