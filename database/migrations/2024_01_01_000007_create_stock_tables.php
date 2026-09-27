<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RETURN TO SUPPLIER (expired / near expiry / damaged / wrong item)
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 40)->nullable()->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('return_date');
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('refund_type', ['adjust_balance', 'cash'])->default('adjust_balance');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('return_date');
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('medicine_batch_id')->nullable()->index();
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('price', 10, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('medicine_batch_id')->nullable();
            $table->enum('type', ['add', 'subtract']);
            $table->unsignedInteger('quantity');
            $table->enum('reason', ['expired', 'damaged', 'lost', 'correction', 'other']);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });

        // Ledger of EVERY stock change
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('medicine_batch_id')->nullable();
            // purchase, purchase_void, purchase_return, sale, sale_void, sale_return, adjustment_in, adjustment_out
            $table->string('type', 30);
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->string('reference', 60)->nullable();
            $table->string('note')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['medicine_id', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
