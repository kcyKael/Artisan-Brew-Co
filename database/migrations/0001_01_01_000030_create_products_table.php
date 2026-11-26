<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('name', 100);
            $table->decimal('price', 10, 2);
            $table->integer('stock_quantity')->default(0);
            $table->string('image', 255)->nullable();
            $table->dateTime('created_at');

            $table->integer('category_id')->nullable();
            $table->string('sku', 40)->nullable()->unique();
            $table->string('description', 500)->nullable();
            $table->integer('low_stock_threshold')->default(5);
            $table->boolean('is_active')->default(true);
            $table->integer('stock_version')->default(1);
            $table->dateTime('archived_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->foreign('category_id', 'products_category_fk')
                ->references('id')
                ->on('categories')
                ->nullOnDelete();

            $table->index(['is_active', 'archived_at', 'category_id'], 'products_pos_filter_idx');
            $table->index('stock_quantity', 'products_stock_idx');
            $table->index('name', 'products_name_idx');
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_price CHECK (price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_stock CHECK (stock_quantity >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_low_stock CHECK (low_stock_threshold >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
