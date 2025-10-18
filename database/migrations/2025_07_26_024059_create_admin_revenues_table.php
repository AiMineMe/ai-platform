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
        Schema::create('admin_revenues', function (Blueprint $table) {
            $table->id();
            $table->enum('revenue_type', ['subscription', 'mining_fee', 'competition_fee']);
            $table->decimal('amount', 12, 2);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('description');
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['revenue_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_revenues');
    }
};
