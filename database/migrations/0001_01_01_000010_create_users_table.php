<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->integer('id', true);
            $table->string('username', 50)->unique();
            $table->string('full_name', 100);
            $table->string('password', 255);
            $table->string('avatar', 255)->nullable();
            $table->dateTime('created_at');

            $table->string('role', 20)->default('cashier');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['role', 'is_active', 'deleted_at'], 'users_role_active_deleted_idx');
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN ('admin', 'cashier'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
