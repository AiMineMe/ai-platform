<?php

use App\Enums\Wallet\Status;
use App\Enums\Wallet\Type;
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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type')->default(Type::MAIN->value)->index();
            $table->string('currency', 10)->index();
            $table->string('address')->unique();
            $table->decimal('balance', 24, 8)->default(0);
            $table->tinyInteger('status')->default(Status::ACTIVE->value)->index();
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
