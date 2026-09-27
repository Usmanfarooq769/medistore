<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('generic_name', 180)->nullable();
            $table->string('strength', 60)->nullable();           // 500mg, 5ml ...
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('barcode', 100)->nullable()->unique();
            $table->string('unit', 30)->default('strip');
            $table->decimal('purchase_price', 10, 2)->default(0); // latest purchase price
            $table->decimal('sale_price', 10, 2)->default(0);     // default sale price
            $table->unsignedInteger('reorder_level')->default(10);
            $table->string('rack', 50)->nullable();
            $table->integer('total_stock')->default(0);           // cached SUM(batches.quantity) - fast lists
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('generic_name');
            $table->index(['is_active', 'total_stock']);
        });

        // Every purchase line creates a batch (batch no + expiry + own price)
        Schema::create('medicine_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_id')->nullable()->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('purchase_price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->integer('initial_quantity')->default(0);
            $table->integer('quantity')->default(0);              // current remaining qty
            $table->timestamps();

            $table->index(['medicine_id', 'quantity', 'expiry_date']); // FEFO lookup
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('medicines');
    }
};
