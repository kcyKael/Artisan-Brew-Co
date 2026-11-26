<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('product_id');
            $table->string('size_label', 40);
            $table->string('temperature', 20);
            $table->decimal('price_adjustment', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();

            $table->foreign('product_id', 'product_variants_product_fk')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->unique(['product_id', 'size_label', 'temperature'], 'product_variants_unique_combo');
            $table->index(['product_id', 'is_active', 'sort_order'], 'product_variants_lookup_idx');
        });

        DB::statement('ALTER TABLE product_variants ADD CONSTRAINT chk_variants_adjustment CHECK (price_adjustment >= 0)');
        DB::statement("ALTER TABLE product_variants ADD CONSTRAINT chk_variants_temperature CHECK (temperature IN ('hot', 'iced'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
