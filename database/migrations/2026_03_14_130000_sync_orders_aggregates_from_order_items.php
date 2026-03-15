<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->select('id')
            ->orderBy('id')
            ->chunk(200, function ($orders): void {
                foreach ($orders as $order) {
                    $totals = DB::table('order_items')
                        ->where('order_id', $order->id)
                        ->selectRaw('COALESCE(SUM(qty), 0) as items_count, COALESCE(SUM(sum), 0) as total_price')
                        ->first();

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update([
                            'items_count' => (int) ($totals->items_count ?? 0),
                            'total_price' => (int) ($totals->total_price ?? 0),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // no-op: this migration only fixes denormalized aggregates
    }
};
