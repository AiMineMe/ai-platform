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
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('manual');
            $table->string('currency', 10);
            $table->decimal('rate', 8, 2)->default(0.00);
            $table->decimal('min_amount', 15, 8)->default(0);
            $table->decimal('max_amount', 15, 8)->default(0);
            $table->decimal('fixed_charge', 15, 8)->default(0);
            $table->decimal('percent_charge', 5, 2)->default(0);
            $table->text('description')->nullable();
            $table->json('credentials')->nullable();
            $table->json('parameters')->nullable();
            $table->boolean('status')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
