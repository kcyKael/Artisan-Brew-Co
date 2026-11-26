<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('name', 80)->unique();
            $table->string('slug', 90)->unique();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_drink')->default(true);
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at');
            $table->dateTime('updated_at')->nullable();

            $table->index(['is_active', 'sort_order'], 'categories_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
