<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            if (!Schema::hasColumn('medicines', 'pack_size')) {
                $table->unsignedInteger('pack_size')->default(1)->after('unit');
            }
            if (!Schema::hasColumn('medicines', 'stock_units_are_base')) {
                $table->boolean('stock_units_are_base')->default(false)->after('pack_size');
            }
        });
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'sale_unit')) $table->string('sale_unit', 20)->default('unit')->after('quantity');
            if (!Schema::hasColumn('sale_items', 'sale_quantity')) $table->decimal('sale_quantity', 12, 3)->default(1)->after('sale_unit');
            if (!Schema::hasColumn('sale_items', 'pack_size')) $table->unsignedInteger('pack_size')->default(1)->after('sale_quantity');
        });
        // Per-base-unit prices need four decimals so pack conversion does not distort MRP/COGS.
        Schema::table('medicine_batches', function (Blueprint $table) {
            $table->decimal('purchase_price', 12, 4)->default(0)->change();
            $table->decimal('sale_price', 12, 4)->default(0)->change();
        });
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('price', 12, 4)->default(0)->change();
            $table->decimal('cost_price', 12, 4)->default(0)->change();
        });
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->decimal('price', 12, 4)->default(0)->change();
        });
        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->decimal('price', 12, 4)->default(0)->change();
        });

        Schema::create('cashier_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_cash', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->default(0);
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'closed_at']);
        });
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'cashier_session_id')) $table->foreignId('cashier_session_id')->nullable()->after('user_id')->constrained('cashier_sessions')->nullOnDelete();
        });
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->string('category', 60)->default('other');
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('cash_transactions');
        Schema::table('sales', function (Blueprint $table) { if (Schema::hasColumn('sales', 'cashier_session_id')) $table->dropConstrainedForeignId('cashier_session_id'); });
        Schema::dropIfExists('cashier_sessions');
        Schema::table('medicine_batches', function (Blueprint $table) { $table->decimal('purchase_price', 10, 2)->default(0)->change(); $table->decimal('sale_price', 10, 2)->default(0)->change(); });
        Schema::table('sale_items', function (Blueprint $table) { $table->decimal('price', 10, 2)->change(); $table->decimal('cost_price', 10, 2)->default(0)->change(); if (Schema::hasColumn('sale_items', 'sale_quantity')) $table->dropColumn(['sale_quantity', 'sale_unit', 'pack_size']); });
        Schema::table('sale_return_items', function (Blueprint $table) { $table->decimal('price', 10, 2)->change(); });
        Schema::table('purchase_return_items', function (Blueprint $table) { $table->decimal('price', 10, 2)->change(); });
        Schema::table('medicines', function (Blueprint $table) { if (Schema::hasColumn('medicines', 'stock_units_are_base')) $table->dropColumn('stock_units_are_base'); if (Schema::hasColumn('medicines', 'pack_size')) $table->dropColumn('pack_size'); });
    }
};
