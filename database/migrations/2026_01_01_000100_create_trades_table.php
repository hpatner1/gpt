<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('coin', 20)->index();
            $table->decimal('capital', 15, 2);
            $table->decimal('risk_percent', 5, 2)->default(1.00);
            $table->decimal('risk_amount', 15, 2);
            $table->decimal('stop_loss_percent', 8, 4);
            $table->decimal('position_size', 20, 8);
            $table->decimal('entry_price', 20, 8);
            $table->decimal('take_profit_price', 20, 8);
            $table->enum('result', ['win', 'loss', 'pending'])->default('pending')->index();
            $table->decimal('profit_loss', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
