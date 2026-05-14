<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cart_items', 'option_key')) {
            $duplicates = DB::table('cart_items')
                ->select('cart_id', 'dessert_id')
                ->selectRaw('MIN(id) as keep_id')
                ->selectRaw('SUM(qty) as total_qty')
                ->groupBy('cart_id', 'dessert_id')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            foreach ($duplicates as $duplicate) {
                DB::table('cart_items')
                    ->where('id', $duplicate->keep_id)
                    ->update(['qty' => $duplicate->total_qty]);

                DB::table('cart_items')
                    ->where('cart_id', $duplicate->cart_id)
                    ->where('dessert_id', $duplicate->dessert_id)
                    ->where('id', '<>', $duplicate->keep_id)
                    ->delete();
            }
        }

        Schema::table('cart_items', function (Blueprint $table) {
            if (Schema::hasColumn('cart_items', 'option_key')) {
                $table->dropUnique('uniq_cart_dessert_option');
                $table->dropColumn(['option_key', 'option_title', 'option_ml']);
                $table->unique(['cart_id', 'dessert_id'], 'uniq_cart_dessert');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'option_key')) {
                $table->dropColumn(['option_key', 'option_title', 'option_ml']);
            }
        });

        Schema::table('desserts', function (Blueprint $table) {
            if (Schema::hasColumn('desserts', 'size_options')) {
                $table->dropColumn('size_options');
            }
        });
    }

    public function down(): void
    {
        Schema::table('desserts', function (Blueprint $table) {
            if (!Schema::hasColumn('desserts', 'size_options')) {
                $table->json('size_options')->nullable()->after('photos');
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            if (!Schema::hasColumn('cart_items', 'option_key')) {
                $table->dropUnique('uniq_cart_dessert');
                $table->string('option_key')->default('default')->after('dessert_id');
                $table->string('option_title')->nullable()->after('option_key');
                $table->unsignedInteger('option_ml')->nullable()->after('option_title');
                $table->unique(['cart_id', 'dessert_id', 'option_key'], 'uniq_cart_dessert_option');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'option_key')) {
                $table->string('option_key')->default('default')->after('dessert_id');
                $table->string('option_title')->nullable()->after('option_key');
                $table->unsignedInteger('option_ml')->nullable()->after('option_title');
            }
        });
    }
};
