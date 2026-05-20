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
            if (! Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number', 10)->nullable()->after('id');
            }
        });

        $usedNumbers = DB::table('orders')
            ->whereNotNull('order_number')
            ->pluck('order_number')
            ->map(fn ($number) => (string) $number)
            ->all();
        $usedNumbers = array_fill_keys($usedNumbers, true);

        DB::table('orders')
            ->whereNull('order_number')
            ->orderBy('created_at')
            ->orderBy('id')
            ->select(['id'])
            ->cursor()
            ->each(function ($order) use (&$usedNumbers): void {
                do {
                    $number = str_pad((string) random_int(0, 9_999_999_999), 10, '0', STR_PAD_LEFT);
                } while (isset($usedNumbers[$number]));

                $usedNumbers[$number] = true;

                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['order_number' => $number]);
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'order_number')) {
                $table->dropUnique(['order_number']);
                $table->dropColumn('order_number');
            }
        });
    }
};
