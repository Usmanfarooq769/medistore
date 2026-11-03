<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Expand the existing enum without changing existing admin/cashier users.
        Schema::table('users', function (Blueprint $t) {
            $t->enum('role', ['admin','branch_manager','pharmacist','cashier'])->default('cashier')->change();
        });

        Schema::create('doctors', function (Blueprint $t) {
            $t->id(); $t->string('name',150); $t->string('license_no',100)->nullable()->unique();
            $t->string('specialty',120)->nullable(); $t->string('phone',40)->nullable(); $t->string('email')->nullable();
            $t->string('address')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
            $t->index(['name','is_active']);
        });
        Schema::create('clinics', function (Blueprint $t) {
            $t->id(); $t->string('name',180); $t->string('phone',40)->nullable(); $t->string('email')->nullable();
            $t->string('address')->nullable(); $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('clinic_doctor', function (Blueprint $t) {
            $t->id(); $t->foreignId('clinic_id')->constrained()->cascadeOnDelete();
            $t->foreignId('doctor_id')->constrained()->cascadeOnDelete(); $t->timestamps();
            $t->unique(['clinic_id','doctor_id']);
        });
        Schema::create('doctor_referrals', function (Blueprint $t) {
            $t->id(); $t->foreignId('doctor_id')->constrained()->restrictOnDelete();
            $t->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->string('reference_no',50)->unique(); $t->date('referred_at'); $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
            $t->index(['branch_id','referred_at']);
        });
        Schema::table('prescriptions', function (Blueprint $t) {
            if (!Schema::hasColumn('prescriptions','doctor_id')) $t->foreignId('doctor_id')->nullable()->after('customer_id')->constrained('doctors')->nullOnDelete();
            if (!Schema::hasColumn('prescriptions','clinic_id')) $t->foreignId('clinic_id')->nullable()->after('doctor_id')->constrained('clinics')->nullOnDelete();
            if (!Schema::hasColumn('prescriptions','referral_id')) $t->foreignId('referral_id')->nullable()->after('clinic_id')->constrained('doctor_referrals')->nullOnDelete();
        });

        Schema::create('manufacturer_schemes', function (Blueprint $t) {
            $t->id(); $t->string('name',180); $t->foreignId('manufacturer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('scheme_type',['buy_x_get_y','discount'])->default('buy_x_get_y');
            $t->unsignedInteger('buy_quantity')->default(1); $t->unsignedInteger('free_quantity')->default(0);
            $t->decimal('discount_percent',7,3)->default(0); $t->date('valid_from'); $t->date('valid_to');
            $t->unsignedInteger('max_free_quantity')->nullable(); $t->boolean('is_active')->default(true);
            $t->text('terms')->nullable(); $t->timestamps();
            $t->index(['is_active','valid_from','valid_to']);
        });
        Schema::table('purchase_items', function (Blueprint $t) {
            if (!Schema::hasColumn('purchase_items','scheme_id')) $t->foreignId('scheme_id')->nullable()->after('medicine_batch_id')->constrained('manufacturer_schemes')->nullOnDelete();
        });
        Schema::table('purchase_order_items', function (Blueprint $t) {
            if (!Schema::hasColumn('purchase_order_items','scheme_id')) $t->foreignId('scheme_id')->nullable()->after('medicine_id')->constrained('manufacturer_schemes')->nullOnDelete();
            if (!Schema::hasColumn('purchase_order_items','scheme_free_qty')) $t->unsignedInteger('scheme_free_qty')->default(0)->after('bonus_qty');
        });
        Schema::create('manufacturer_scheme_redemptions', function (Blueprint $t) {
            $t->id(); $t->foreignId('scheme_id')->constrained('manufacturer_schemes')->restrictOnDelete();
            $t->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete(); $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('paid_quantity'); $t->unsignedInteger('free_quantity')->default(0);
            $t->decimal('purchase_value',14,2)->default(0); $t->decimal('estimated_saving',14,2)->default(0);
            $t->date('redeemed_at'); $t->timestamps();
            $t->index(['scheme_id','redeemed_at']);
        });

        Schema::create('expiry_return_claims', function (Blueprint $t) {
            $t->id(); $t->string('claim_no',50)->unique(); $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->enum('status',['draft','submitted','approved','rejected','credited'])->default('draft');
            $t->decimal('requested_amount',14,2)->default(0); $t->decimal('approved_amount',14,2)->default(0);
            $t->text('notes')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('credited_at')->nullable(); $t->timestamps(); $t->index(['branch_id','status']);
        });
        Schema::create('expiry_return_claim_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('claim_id')->constrained('expiry_return_claims')->cascadeOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $t->foreignId('batch_id')->constrained('medicine_batches')->restrictOnDelete();
            $t->unsignedInteger('quantity'); $t->decimal('unit_cost',12,2); $t->decimal('line_total',14,2);
            $t->timestamps(); $t->unique(['claim_id','batch_id']);
        });
        Schema::create('supplier_credit_notes', function (Blueprint $t) {
            $t->id(); $t->string('credit_note_no',50)->unique(); $t->foreignId('claim_id')->unique()->constrained('expiry_return_claims')->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete(); $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->decimal('amount',14,2); $t->date('credit_date'); $t->string('reference',100)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });

        Schema::create('insurance_providers', function (Blueprint $t) {
            $t->id(); $t->string('name',180); $t->string('code',50)->unique(); $t->string('phone',40)->nullable();
            $t->string('email')->nullable(); $t->string('claims_email')->nullable(); $t->string('address')->nullable();
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('insurance_plans', function (Blueprint $t) {
            $t->id(); $t->foreignId('provider_id')->constrained('insurance_providers')->cascadeOnDelete();
            $t->string('name',150); $t->string('plan_code',80)->nullable(); $t->decimal('annual_limit',14,2)->nullable();
            $t->decimal('default_coverage_percent',7,3)->default(0); $t->boolean('is_active')->default(true); $t->timestamps();
            $t->unique(['provider_id','name']);
        });
        Schema::create('insurance_coverages', function (Blueprint $t) {
            $t->id(); $t->foreignId('plan_id')->constrained('insurance_plans')->cascadeOnDelete();
            $t->foreignId('medicine_id')->nullable()->constrained()->cascadeOnDelete();
            $t->decimal('coverage_percent',7,3); $t->decimal('max_amount',14,2)->nullable();
            $t->boolean('is_covered')->default(true); $t->timestamps();
            $t->unique(['plan_id','medicine_id']);
        });
        Schema::table('customers', function (Blueprint $t) {
            if (!Schema::hasColumn('customers','insurance_provider_id')) $t->foreignId('insurance_provider_id')->nullable()->constrained('insurance_providers')->nullOnDelete();
            if (!Schema::hasColumn('customers','insurance_plan_id')) $t->foreignId('insurance_plan_id')->nullable()->constrained('insurance_plans')->nullOnDelete();
            if (!Schema::hasColumn('customers','insurance_member_no')) $t->string('insurance_member_no',100)->nullable();
        });
        Schema::create('insurance_claims', function (Blueprint $t) {
            $t->id(); $t->string('claim_no',50)->unique(); $t->foreignId('provider_id')->constrained('insurance_providers')->restrictOnDelete();
            $t->foreignId('plan_id')->nullable()->constrained('insurance_plans')->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete(); $t->date('claim_date');
            $t->decimal('gross_amount',14,2)->default(0); $t->decimal('covered_amount',14,2)->default(0); $t->decimal('copay_amount',14,2)->default(0);
            $t->decimal('settled_amount',14,2)->default(0);
            $t->enum('status',['draft','submitted','approved','partially_settled','settled','rejected'])->default('draft');
            $t->string('external_reference',100)->nullable(); $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
            $t->index(['provider_id','status','claim_date']);
        });
        Schema::create('insurance_claim_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('claim_id')->constrained('insurance_claims')->cascadeOnDelete();
            $t->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete(); $t->string('medicine_name',180);
            $t->unsignedInteger('quantity'); $t->decimal('unit_price',12,2); $t->decimal('gross_amount',14,2);
            $t->decimal('covered_amount',14,2); $t->decimal('copay_amount',14,2); $t->timestamps();
        });

        Schema::create('insurance_claim_settlements', function (Blueprint $t) {
            $t->id(); $t->foreignId('claim_id')->constrained('insurance_claims')->cascadeOnDelete();
            $t->decimal('amount',14,2); $t->string('reference',100)->nullable(); $t->date('payment_date');
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
            $t->index(['claim_id','payment_date']);
        });

        if (Schema::hasTable('accounting_accounts')) {
            DB::table('accounting_accounts')->insertOrIgnore([
                'code'=>'1210','name'=>'Insurance Receivable','type'=>'asset','is_system'=>true,'is_active'=>true,
                'created_at'=>now(),'updated_at'=>now()
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_settlements'); Schema::dropIfExists('insurance_claim_items'); Schema::dropIfExists('insurance_claims');
        Schema::table('customers', function (Blueprint $t) {
            foreach (['insurance_provider_id','insurance_plan_id'] as $c) if (Schema::hasColumn('customers',$c)) $t->dropConstrainedForeignId($c);
            if (Schema::hasColumn('customers','insurance_member_no')) $t->dropColumn('insurance_member_no');
        });
        Schema::dropIfExists('insurance_coverages'); Schema::dropIfExists('insurance_plans'); Schema::dropIfExists('insurance_providers');
        Schema::dropIfExists('supplier_credit_notes'); Schema::dropIfExists('expiry_return_claim_items'); Schema::dropIfExists('expiry_return_claims');
        Schema::dropIfExists('manufacturer_scheme_redemptions');
        Schema::table('purchase_order_items', function (Blueprint $t) { if (Schema::hasColumn('purchase_order_items','scheme_id')) $t->dropConstrainedForeignId('scheme_id'); if (Schema::hasColumn('purchase_order_items','scheme_free_qty')) $t->dropColumn('scheme_free_qty'); });
        Schema::table('purchase_items', function (Blueprint $t) { if (Schema::hasColumn('purchase_items','scheme_id')) $t->dropConstrainedForeignId('scheme_id'); });
        Schema::dropIfExists('manufacturer_schemes');
        Schema::table('prescriptions', function (Blueprint $t) {
            foreach (['referral_id','clinic_id','doctor_id'] as $c) if (Schema::hasColumn('prescriptions',$c)) $t->dropConstrainedForeignId($c);
        });
        Schema::dropIfExists('doctor_referrals'); Schema::dropIfExists('clinic_doctor'); Schema::dropIfExists('clinics'); Schema::dropIfExists('doctors');
        if (Schema::hasTable('accounting_accounts')) DB::table('accounting_accounts')->where('code','1210')->delete();
        DB::table('users')->whereIn('role',['branch_manager','pharmacist'])->update(['role'=>'cashier']);
        Schema::table('users', function (Blueprint $t) {
            $t->enum('role', ['admin','cashier'])->default('cashier')->change();
        });
    }
};
