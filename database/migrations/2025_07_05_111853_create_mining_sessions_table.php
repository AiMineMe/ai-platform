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
        Schema::create('mining_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('session_type')->default('standard');
            $table->decimal('mining_rate', 10, 8)->default(0.00001);
            $table->decimal('total_mined', 20, 8)->default(0);
            $table->decimal('current_balance', 20, 8)->default(0);
            $table->integer('level')->default(1);
            $table->integer('experience_points')->default(0);
            $table->integer('streak_days')->default(0);
            $table->decimal('multiplier', 3, 2)->default(1.0);
            $table->timestamp('last_claim_at')->nullable();
            $table->timestamp('session_started_at')->nullable();
            $table->timestamp('session_ends_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('achievements')->nullable();
            $table->json('boosts')->nullable();
            $table->timestamp('last_update_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_active']);
            $table->index('level');
            $table->index('total_mined');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mining_sessions');
    }
};
