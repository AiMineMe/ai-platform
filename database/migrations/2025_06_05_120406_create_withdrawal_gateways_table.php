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
        Schema::create('withdrawal_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('currency', 10);
            $table->decimal('rate', 5, 2)->nullable();
            $table->decimal('min_amount', 20, 8)->default(0);
            $table->decimal('max_amount', 20, 8)->default(0);
            $table->decimal('fixed_charge', 20, 8)->default(0);
            $table->decimal('percent_charge', 8, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->json('parameters')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_gateways');
    }
};
