<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 40)->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->date('sale_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->decimal('due', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->decimal('returned_amount', 12, 2)->default(0);
            $table->string('payment_method', 20)->default('cash');
            $table->enum('payment_status', ['paid', 'partial', 'unpaid'])->default('paid');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('sale_date');
            $table->index(['customer_id', 'sale_date']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('medicine_batch_id')->nullable()->index();
            $table->string('medicine_name', 180);
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('returned_qty')->default(0);
            $table->decimal('price', 10, 2);
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        /*
         * CUSTOMER RETURNS
         *  - with invoice  : sale_id set, items linked to sale_items
         *  - without invoice: sale_id NULL, medicine + batch chosen manually
         */
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 40)->nullable()->unique();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->date('return_date');
            $table->decimal('subtotal', 12, 2)->default(0);        // value of returned items
            $table->decimal('deduction', 12, 2)->default(0);       // restocking fee / cut
            $table->decimal('total', 12, 2)->default(0);           // subtotal - deduction
            $table->decimal('due_adjusted', 12, 2)->default(0);    // used to reduce customer's due
            $table->decimal('refund_amount', 12, 2)->default(0);   // paid back to customer
            $table->enum('refund_method', ['cash', 'card', 'online', 'none'])->default('cash');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index('return_date');
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('medicine_batch_id')->nullable()->index();
            $table->string('medicine_name', 180);
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('price', 10, 2);
            $table->decimal('total', 12, 2);
            $table->enum('condition', ['good', 'damaged', 'expired'])->default('good');
            $table->boolean('restocked')->default(false);          // true => added back to stock
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
