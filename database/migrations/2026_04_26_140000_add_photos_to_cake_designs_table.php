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
            $table->json('photos')->nullable()->after('image_url');
        });

        DB::table('cake_designs')
            ->whereNotNull('image_url')
            ->orderBy('id')
            ->chunkById(100, function ($designs) {
                foreach ($designs as $design) {
                    DB::table('cake_designs')
                        ->where('id', $design->id)
                        ->update(['photos' => json_encode([$design->image_url], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('cake_designs', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};
