<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'credit_limit')) $table->decimal('credit_limit', 12, 2)->nullable()->after('balance');
            if (!Schema::hasColumn('customers', 'loyalty_points')) $table->integer('loyalty_points')->default(0)->after('credit_limit');
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 40)->unique();
            $table->enum('discount_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('discount_value', 12, 2);
            $table->decimal('minimum_subtotal', 12, 2)->default(0);
            $table->decimal('maximum_discount', 12, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'promotion_id')) $table->foreignId('promotion_id')->nullable()->after('tax_profile_id')->constrained('promotions')->nullOnDelete();
            if (!Schema::hasColumn('sales', 'loyalty_points_earned')) $table->unsignedInteger('loyalty_points_earned')->default(0)->after('promotion_id');
            if (!Schema::hasColumn('sales', 'loyalty_points_redeemed')) $table->unsignedInteger('loyalty_points_redeemed')->default(0)->after('loyalty_points_earned');
            if (!Schema::hasColumn('sales', 'loyalty_points_reversed')) $table->unsignedInteger('loyalty_points_reversed')->default(0)->after('loyalty_points_redeemed');
            if (!Schema::hasColumn('sales', 'loyalty_points_restored')) $table->unsignedInteger('loyalty_points_restored')->default(0)->after('loyalty_points_reversed');
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['earned', 'redeemed', 'reversed', 'restored', 'adjusted']);
            $table->unsignedInteger('points');
            $table->integer('balance_after');
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamps();
            $table->unique('sale_id');
            $table->index(['promotion_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('loyalty_transactions');
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'promotion_id')) $table->dropConstrainedForeignId('promotion_id');
            foreach (['loyalty_points_earned', 'loyalty_points_redeemed', 'loyalty_points_reversed', 'loyalty_points_restored'] as $column) if (Schema::hasColumn('sales', $column)) $table->dropColumn($column);
        });
        Schema::dropIfExists('promotions');
        Schema::table('customers', function (Blueprint $table) {
            foreach (['credit_limit', 'loyalty_points'] as $column) if (Schema::hasColumn('customers', $column)) $table->dropColumn($column);
        });
    }
};
