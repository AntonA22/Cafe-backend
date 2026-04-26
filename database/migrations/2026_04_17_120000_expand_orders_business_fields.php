<?php

use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'subtotal_price')) {
                $table->integer('subtotal_price')->default(0)->after('items_count');
            }
            if (!Schema::hasColumn('orders', 'delivery_fee')) {
                $table->integer('delivery_fee')->default(0)->after('subtotal_price');
            }
            if (!Schema::hasColumn('orders', 'delivery_mode')) {
                $table->string('delivery_mode')->default(Order::DELIVERY_MODE_DELIVERY)->after('comment');
            }
            if (!Schema::hasColumn('orders', 'payment_mode')) {
                $table->string('payment_mode')->nullable()->after('delivery_mode');
            }
            if (!Schema::hasColumn('orders', 'leave_at_door')) {
                $table->boolean('leave_at_door')->default(false)->after('payment_mode');
            }
            if (!Schema::hasColumn('orders', 'customer_phone')) {
                $table->string('customer_phone', 50)->nullable()->after('leave_at_door');
            }
        });

        DB::table('orders')
            ->whereNull('delivery_mode')
            ->update(['delivery_mode' => Order::DELIVERY_MODE_DELIVERY]);

        DB::table('orders')
            ->where('status', 'canceled')
            ->update(['status' => Order::STATUS_CANCELLED]);

        DB::statement('UPDATE orders SET subtotal_price = total_price WHERE subtotal_price = 0');

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('address_id')
                    ->references('id')
                    ->on('addresses')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['address_id']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'subtotal_price',
                'delivery_fee',
                'delivery_mode',
                'payment_mode',
                'leave_at_door',
                'customer_phone',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
