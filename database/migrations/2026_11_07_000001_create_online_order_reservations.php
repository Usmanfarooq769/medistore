<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('online_order_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained('online_orders')->cascadeOnDelete();
            $table->foreignId('medicine_batch_id')->constrained('medicine_batches')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->enum('status', ['active', 'consumed', 'released'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['medicine_batch_id', 'status', 'expires_at'], 'order_reservations_batch_status_expiry_idx');
            $table->index(['online_order_id', 'status'], 'order_reservations_order_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_order_reservations');
    }
};
