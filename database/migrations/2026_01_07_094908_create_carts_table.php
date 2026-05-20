<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_carts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            // Если корзина привязана к юзеру (рекомендую)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // // Если хочешь корзину для гостя — можно хранить ключ с клиента
            // $table->string('session_key')->nullable()->index();

            $table->timestamps();

            // Чтобы у пользователя была одна активная корзина (опционально)
            $table->unique(['user_id'], 'uniq_cart_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
