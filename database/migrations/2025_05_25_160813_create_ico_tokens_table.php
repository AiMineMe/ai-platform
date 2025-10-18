<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ico_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('symbol', 10);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 4);
            $table->decimal('current_price', 10, 4)->nullable();
            $table->timestamp('price_updated_at')->nullable();
            $table->bigInteger('total_supply');
            $table->bigInteger('tokens_sold')->default(0);
            $table->date('sale_start_date');
            $table->date('sale_end_date');
            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])->default('active');
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ico_tokens');
    }
};
