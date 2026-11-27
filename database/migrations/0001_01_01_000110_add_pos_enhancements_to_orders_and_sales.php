<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_type', 20)->default('dine_in')->after('source');
            $table->string('payment_method', 20)->default('cash')->after('order_type');
            $table->string('payment_reference', 80)->nullable()->after('payment_method');
            $table->string('cup_name', 60)->nullable()->after('payment_reference');
            $table->string('order_note', 200)->nullable()->after('cup_name');
            $table->string('discount_type', 20)->default('none')->after('subtotal_amount');
            $table->string('discount_label', 80)->nullable()->after('discount_type');
            $table->decimal('discount_amount', 10, 2)->default(0.00)->after('discount_label');
            $table->integer('gift_card_id')->nullable()->after('discount_amount');
            $table->string('status', 20)->default('completed')->after('change_amount');
            $table->dateTime('voided_at')->nullable()->after('status');
            $table->integer('voided_by')->nullable()->after('voided_at');
            $table->string('void_reason', 255)->nullable()->after('voided_by');

            $table->foreign('gift_card_id', 'orders_gift_card_fk')
                ->references('id')
                ->on('gift_cards')
                ->nullOnDelete();

            $table->foreign('voided_by', 'orders_voided_by_fk')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();

            $table->index('status', 'orders_status_idx');
            $table->index('gift_card_id', 'orders_gift_card_idx');
            $table->index('voided_by', 'orders_voided_by_idx');
        });

        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_orders_order_type CHECK (order_type IN ('dine_in', 'take_out'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_orders_payment_method CHECK (payment_method IN ('cash', 'card', 'gcash'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_orders_discount_type CHECK (discount_type IN ('none', 'percent', 'fixed', 'giftcard'))");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT chk_orders_status CHECK (status IN ('completed', 'voided'))");
        DB::statement('ALTER TABLE orders ADD CONSTRAINT chk_orders_discount_amount CHECK (discount_amount >= 0)');

        Schema::table('sales', function (Blueprint $table) {
            $table->string('note', 120)->nullable()->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('note');
        });

        DB::statement('ALTER TABLE orders DROP CONSTRAINT chk_orders_order_type');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT chk_orders_payment_method');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT chk_orders_discount_type');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT chk_orders_status');
        DB::statement('ALTER TABLE orders DROP CONSTRAINT chk_orders_discount_amount');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('orders_gift_card_fk');
            $table->dropForeign('orders_voided_by_fk');
            $table->dropIndex('orders_status_idx');
            $table->dropIndex('orders_gift_card_idx');
            $table->dropIndex('orders_voided_by_idx');

            $table->dropColumn([
                'order_type',
                'payment_method',
                'payment_reference',
                'cup_name',
                'order_note',
                'discount_type',
                'discount_label',
                'discount_amount',
                'gift_card_id',
                'status',
                'voided_at',
                'voided_by',
                'void_reason',
            ]);
        });
    }
};
