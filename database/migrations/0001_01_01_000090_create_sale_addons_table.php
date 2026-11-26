<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_addons', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->integer('sale_id');
            $table->integer('addon_id')->nullable();
            $table->string('addon_name_snapshot', 80);
            $table->decimal('addon_price_snapshot', 10, 2);
            $table->dateTime('created_at');

            $table->foreign('sale_id', 'sale_addons_sale_fk')
                ->references('id')
                ->on('sales')
                ->cascadeOnDelete();

            $table->foreign('addon_id', 'sale_addons_addon_fk')
                ->references('id')
                ->on('addons')
                ->nullOnDelete();

            $table->index('sale_id', 'sale_addons_sale_idx');
        });

        DB::statement('ALTER TABLE sale_addons ADD CONSTRAINT chk_sale_addons_price CHECK (addon_price_snapshot >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_addons');
    }
};
