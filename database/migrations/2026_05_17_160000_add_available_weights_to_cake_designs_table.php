<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cake_designs', function (Blueprint $table) {
            $table->json('available_weights')->nullable()->after('weight_grams');
        });

        DB::table('cake_designs')
            ->orderBy('id')
            ->get(['id', 'weight_grams'])
            ->each(function ($design) {
                DB::table('cake_designs')
                    ->where('id', $design->id)
                    ->update([
                            'available_weights' => json_encode($this->fixedWeightOptions(), JSON_UNESCAPED_UNICODE),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('cake_designs', function (Blueprint $table) {
            $table->dropColumn('available_weights');
        });
    }

    private function fixedWeightOptions(): array
    {
        return [
            ['title' => '0,8 кг', 'grams' => 800],
            ['title' => '1,2 кг', 'grams' => 1200],
            ['title' => '1,5 кг', 'grams' => 1500],
        ];
    }
};
