<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('order_id');
            $table->foreignId('dessert_id')->constrained('desserts')->cascadeOnDelete();

            $table->integer('qty');
            $table->integer('price'); // цена за 1 (в копейках) на момент заказа
            $table->integer('sum');   // price * qty

            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index(['order_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('order_items');
    }
};