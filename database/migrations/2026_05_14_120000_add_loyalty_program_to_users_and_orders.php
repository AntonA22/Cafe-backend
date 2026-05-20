<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('bonus_points')->default(0)->after('is_staff');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('bonus_points_spent')->default(0)->after('delivery_fee');
            $table->unsignedInteger('bonus_points_earned')->default(0)->after('bonus_points_spent');
            $table->timestamp('bonus_points_refunded_at')->nullable()->after('bonus_points_earned');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'bonus_points_spent',
                'bonus_points_earned',
                'bonus_points_refunded_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('bonus_points');
        });
    }
};
