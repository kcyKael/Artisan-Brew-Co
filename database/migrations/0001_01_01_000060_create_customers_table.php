<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('full_name', 100);
            $table->string('email', 100);
            $table->string('phone', 20)->nullable();
            $table->dateTime('created_at');

            $table->string('notes', 255)->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index('full_name', 'customers_full_name_idx');
            $table->index('email', 'customers_email_idx');
            $table->index('deleted_at', 'customers_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
