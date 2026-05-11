<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cake_designs', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('subtitle');
            $table->string('image_path')->nullable();
            $table->text('filling');
            $table->text('accent');
            $table->text('composition');
            $table->string('storage');
            $table->unsignedInteger('weight_grams');
            $table->decimal('price', 8, 2);
            $table->unsignedInteger('calories_per_100g')->nullable();
            $table->string('recommended_text')->nullable();
            $table->boolean('available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cake_designs');
    }
};
