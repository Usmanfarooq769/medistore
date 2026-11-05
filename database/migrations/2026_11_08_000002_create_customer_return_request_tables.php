<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 50)->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_return_id')->nullable()->constrained('sale_returns')->nullOnDelete();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, completed
            $table->string('reason', 120);
            $table->text('customer_note')->nullable();
            $table->text('staff_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status', 'created_at'], 'cust_return_branch_status_created_idx');
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('customer_return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['customer_return_request_id', 'sale_item_id'], 'cust_return_req_sale_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_return_request_items');
        Schema::dropIfExists('customer_return_requests');
    }
};
