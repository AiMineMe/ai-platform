<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('trade_id');
            $table->foreignId('trade_setting_id')->constrained('trade_settings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symbol');
            $table->enum('direction', ['up', 'down']);
            $table->decimal('amount', 15,);
            $table->decimal('open_price', 15, 8);
            $table->decimal('close_price', 15, 8)->nullable();
            $table->integer('duration_seconds');
            $table->decimal('payout_rate', 5, 2);
            $table->timestamp('open_time');
            $table->timestamp('expiry_time');
            $table->timestamp('close_time')->nullable();
            $table->enum('status', ['active', 'won', 'lost', 'draw', 'cancelled', 'expired'])->default('active');
            $table->decimal('profit_loss', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['symbol', 'status']);
            $table->index(['expiry_time', 'status']);
            $table->index(['open_time', 'close_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
