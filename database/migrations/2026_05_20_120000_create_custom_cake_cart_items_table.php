<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_cake_cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('price');
            $table->json('payload');
            $table->timestamps();

            $table->index('cart_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_cake_cart_items');
    }
};
