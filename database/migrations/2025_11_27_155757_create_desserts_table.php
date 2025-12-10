<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desserts', function (Blueprint $table) {
            $table->id(); // автоинкрементный id
            $table->string('name'); // имя десерта
            $table->string('category'); // категория десерта
            $table->text('description'); // описание десерта
            $table->decimal('price', 8, 2); // цена десерта
            $table->json('photos')->nullable(); // массив ссылок на фото
            $table->boolean('available')->default(true); // наличие
            $table->decimal('weight', 6, 2)->nullable(); // вес в граммах
            $table->integer('calories')->nullable(); // калории
            $table->decimal('proteins', 5, 2)->nullable(); // белки
            $table->decimal('fats', 5, 2)->nullable(); // жиры
            $table->decimal('carbohydrates', 5, 2)->nullable(); // углеводы
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desserts');
    }
};