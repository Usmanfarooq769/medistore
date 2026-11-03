<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 50)->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 150);
            $table->string('customer_phone', 40);
            $table->string('delivery_address', 1000)->nullable();
            $table->enum('channel', ['web', 'phone', 'whatsapp', 'manual'])->default('web');
            $table->enum('status', ['pending', 'confirmed', 'ready', 'out_for_delivery', 'delivered', 'cancelled'])->default('pending');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('customer_note')->nullable();
            $table->text('staff_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status', 'created_at'], 'online_orders_branch_status_created_idx');
        });

        Schema::create('online_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 4);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();
        });

        Schema::create('delivery_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('online_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('driver_name', 120);
            $table->string('driver_phone', 40)->nullable();
            $table->enum('status', ['assigned', 'picked_up', 'delivered', 'failed'])->default('assigned');
            $table->text('delivery_note')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
        });

        Schema::create('cold_chain_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('device_code', 60);
            $table->string('name', 150);
            $table->string('location', 180)->nullable();
            $table->decimal('minimum_temperature', 6, 2)->default(2);
            $table->decimal('maximum_temperature', 6, 2)->default(8);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'device_code'], 'cold_chain_branch_device_unique');
        });

        Schema::create('cold_chain_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('cold_chain_devices')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('temperature', 6, 2);
            $table->timestamp('measured_at');
            $table->boolean('is_excursion')->default(false);
            $table->enum('status', ['normal', 'open', 'acknowledged', 'resolved'])->default('normal');
            $table->text('action_taken')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'is_excursion', 'measured_at'], 'cold_chain_branch_excursion_date_idx');
        });

        Schema::create('supplier_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_no', 70)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedSmallInteger('lead_days')->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->string('payment_terms', 180)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'active', 'expired', 'terminated'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['supplier_id', 'status', 'starts_on', 'ends_on']);
        });

        Schema::create('stage_six_event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_six_event_logs');
        Schema::dropIfExists('supplier_contracts');
        Schema::dropIfExists('cold_chain_logs');
        Schema::dropIfExists('cold_chain_devices');
        Schema::dropIfExists('delivery_jobs');
        Schema::dropIfExists('online_order_items');
        Schema::dropIfExists('online_orders');
    }
};
