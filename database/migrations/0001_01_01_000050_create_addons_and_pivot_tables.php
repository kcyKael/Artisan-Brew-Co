<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('name', 80)->unique();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();

            $table->index(['is_active', 'sort_order'], 'addons_active_sort_idx');
        });

        DB::statement('ALTER TABLE addons ADD CONSTRAINT chk_addons_price CHECK (price >= 0)');

        Schema::create('addon_product', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('product_id');
            $table->integer('addon_id');

            $table->primary(['product_id', 'addon_id']);

            $table->foreign('product_id', 'addon_product_product_fk')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign('addon_id', 'addon_product_addon_fk')
                ->references('id')
                ->on('addons')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_product');
        Schema::dropIfExists('addons');
    }
};
