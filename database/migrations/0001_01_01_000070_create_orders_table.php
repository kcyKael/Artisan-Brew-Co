<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('order_number', 32)->unique();
            $table->integer('customer_id')->nullable();
            $table->integer('sold_by');
            $table->string('customer_name_snapshot', 100)->default('Walk-in customer');
            $table->string('staff_name_snapshot', 100);
            $table->string('source', 20)->default('pos');
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('cash_received', 10, 2);
            $table->decimal('change_amount', 10, 2);
            $table->string('checkout_token', 64);
            $table->string('request_hash', 64);
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();

            $table->foreign('customer_id', 'orders_customer_fk')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();

            $table->foreign('sold_by', 'orders_staff_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->unique(['sold_by', 'checkout_token'], 'orders_staff_checkout_token_unique');
            $table->index('created_at', 'orders_created_at_idx');
            $table->index(['sold_by', 'created_at'], 'orders_staff_created_idx');
            $table->index('customer_id', 'orders_customer_idx');
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_total CHECK (total_amount >= 0)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_cash CHECK (cash_received >= total_amount)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_change CHECK (change_amount >= 0)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_orders_source CHECK (source IN ('pos', 'record_sale'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
