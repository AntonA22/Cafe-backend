<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_cart_items_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();

            // предполагаю, что десерты у тебя в таблице desserts (или products/menu_items)
            $table->foreignId('dessert_id')->constrained('desserts')->cascadeOnDelete();

            $table->unsignedInteger('qty')->default(1);

            // цена на момент добавления (чтобы не ломалось, если цена десерта изменится)
            $table->unsignedInteger('price')->nullable();

            $table->timestamps();

            // чтобы один и тот же десерт не дублировался строками — увеличиваем qty
            $table->unique(['cart_id', 'dessert_id'], 'uniq_cart_dessert');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
