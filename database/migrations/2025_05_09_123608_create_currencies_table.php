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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->unique();
            $table->string('name', 100);
            $table->enum('type', ['crypto']);
            $table->decimal('current_price', 28, 8)->default(0);
            $table->decimal('previous_price', 28, 8)->default(0);
            $table->decimal('total_volume', 28, 8)->default(0);
            $table->decimal('market_cap', 28, 8)->default(0);
            $table->integer('rank')->default(0);
            $table->decimal('change_percent', 10, 4)->nullable();
            $table->string('base_currency', 3)->default('USD');
            $table->string('image_url', 500)->nullable();
            $table->string('tradingview_symbol', 50)->nullable();
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
