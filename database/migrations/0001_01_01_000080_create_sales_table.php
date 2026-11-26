<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('product_id');
            $table->integer('customer_id')->nullable();
            $table->integer('sold_by');
            $table->integer('quantity');
            $table->decimal('total_price', 10, 2);
            $table->dateTime('created_at');

            $table->integer('order_id')->nullable();
            $table->integer('product_variant_id')->nullable();
            $table->string('product_name_snapshot', 100);
            $table->string('variant_label_snapshot', 80)->nullable();
            $table->decimal('base_price_snapshot', 10, 2)->default(0.00);
            $table->decimal('variant_adjustment_snapshot', 10, 2)->default(0.00);
            $table->decimal('addons_total_snapshot', 10, 2)->default(0.00);
            $table->decimal('unit_price', 10, 2);
            $table->dateTime('updated_at')->nullable();

            $table->foreign('product_id', 'sales_product_fk')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();

            $table->foreign('customer_id', 'sales_customer_fk')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();

            $table->foreign('sold_by', 'sales_staff_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->foreign('order_id', 'sales_order_fk')
                ->references('id')
                ->on('orders')
                ->restrictOnDelete();

            $table->foreign('product_variant_id', 'sales_variant_fk')
                ->references('id')
                ->on('product_variants')
                ->nullOnDelete();

            $table->index('order_id', 'sales_order_idx');
            $table->index('created_at', 'sales_created_at_idx');
            $table->index(['sold_by', 'created_at'], 'sales_staff_created_idx');
            $table->index('product_id', 'sales_product_idx');
            $table->index('customer_id', 'sales_customer_idx');
        });

        DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_sales_quantity CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_sales_unit_price CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT chk_sales_total_price CHECK (total_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
