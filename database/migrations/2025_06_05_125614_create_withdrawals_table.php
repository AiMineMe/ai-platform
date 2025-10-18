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
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('withdrawal_gateway_id')->constrained()->onDelete('cascade');
            $table->string('trx')->unique();
            $table->decimal('amount', 20, 8);
            $table->decimal('charge', 20, 8)->default(0);
            $table->decimal('final_amount', 20, 8)->default(0);
            $table->decimal('withdrawal_amount', 20, 8)->default(0);
            $table->string('currency', 10);
            $table->decimal('conversion_rate', 20, 8)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->json('user_data');
            $table->text('admin_response')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
