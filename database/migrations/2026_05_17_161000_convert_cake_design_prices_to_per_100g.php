<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cake_designs')
            ->orderBy('id')
            ->get(['id', 'price'])
            ->each(function ($design) {
                $price = (float) $design->price;

                if ($price <= 1000) {
                    return;
                }

                DB::table('cake_designs')
                    ->where('id', $design->id)
                    ->update([
                        'price' => round($price / 10, 2),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('cake_designs')
            ->orderBy('id')
            ->get(['id', 'price'])
            ->each(function ($design) {
                $price = (float) $design->price;

                if ($price > 1000) {
                    return;
                }

                DB::table('cake_designs')
                    ->where('id', $design->id)
                    ->update([
                        'price' => round($price * 10, 2),
                    ]);
            });
    }
};
