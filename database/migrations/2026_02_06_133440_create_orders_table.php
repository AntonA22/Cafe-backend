<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('address_id')->nullable(); // если addresses у тебя uuid
            // если addresses int: $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();

            $table->string('status')->default('new'); // new, paid, cooking, delivering, done, canceled
            $table->integer('items_count')->default(0);
            $table->integer('total_price')->default(0); // храним в копейках/центах

            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('orders');
    }
};