<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('desserts', function (Blueprint $table) {
            $table->json('size_options')->nullable()->after('photos');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('uniq_cart_dessert');
            $table->string('option_key')->default('default')->after('dessert_id');
            $table->string('option_title')->nullable()->after('option_key');
            $table->unsignedInteger('option_ml')->nullable()->after('option_title');
            $table->unique(['cart_id', 'dessert_id', 'option_key'], 'uniq_cart_dessert_option');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('option_key')->default('default')->after('dessert_id');
            $table->string('option_title')->nullable()->after('option_key');
            $table->unsignedInteger('option_ml')->nullable()->after('option_title');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['option_key', 'option_title', 'option_ml']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropUnique('uniq_cart_dessert_option');
            $table->dropColumn(['option_key', 'option_title', 'option_ml']);
            $table->unique(['cart_id', 'dessert_id'], 'uniq_cart_dessert');
        });

        Schema::table('desserts', function (Blueprint $table) {
            $table->dropColumn('size_options');
        });
    }
};
