<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supplier_quotations', function (Blueprint $t) {
            $t->id(); $t->string('quote_no', 50)->unique();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->date('quote_date'); $t->date('valid_until')->nullable();
            $t->enum('status', ['draft','submitted','accepted','rejected','converted'])->default('draft');
            $t->decimal('subtotal',14,2)->default(0); $t->decimal('tax',14,2)->default(0); $t->decimal('total',14,2)->default(0);
            $t->text('notes')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
            $t->index(['branch_id','status','quote_date']);
        });
        Schema::create('supplier_quotation_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('quotation_id')->constrained('supplier_quotations')->cascadeOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete(); $t->unsignedInteger('quantity');
            $t->decimal('unit_price',12,4); $t->decimal('tax_percent',7,3)->default(0); $t->unsignedSmallInteger('lead_days')->default(0);
            $t->string('manufacturer_reference',100)->nullable(); $t->timestamps();
        });

        Schema::create('warehouse_locations', function (Blueprint $t) {
            $t->id(); $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('warehouse_locations')->nullOnDelete();
            $t->string('code',40); $t->string('name',120); $t->enum('location_type',['zone','rack','shelf','bin'])->default('bin');
            $t->boolean('is_active')->default(true); $t->timestamps(); $t->unique(['branch_id','code']);
        });
        Schema::create('batch_location_stocks', function (Blueprint $t) {
            $t->id(); $t->foreignId('medicine_batch_id')->constrained('medicine_batches')->cascadeOnDelete();
            $t->foreignId('location_id')->constrained('warehouse_locations')->cascadeOnDelete(); $t->unsignedInteger('quantity')->default(0);
            $t->timestamps(); $t->unique(['medicine_batch_id','location_id']);
        });
        Schema::create('warehouse_stock_counts', function (Blueprint $t) {
            $t->id(); $t->string('count_no',50)->unique(); $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->date('count_date'); $t->enum('status',['draft','submitted','approved','cancelled'])->default('draft');
            $t->text('notes')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
            $t->index(['branch_id','status','count_date']);
        });
        Schema::create('warehouse_stock_count_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('stock_count_id')->constrained('warehouse_stock_counts')->cascadeOnDelete();
            $t->foreignId('medicine_batch_id')->constrained('medicine_batches')->restrictOnDelete();
            $t->unsignedInteger('system_quantity'); $t->unsignedInteger('counted_quantity'); $t->integer('difference')->default(0); $t->timestamps();
            $t->unique(['stock_count_id','medicine_batch_id'],'wsc_items_batch_unique');
        });

        Schema::create('healthcare_facilities', function (Blueprint $t) {
            $t->id(); $t->string('name',180); $t->enum('facility_type',['hospital','clinic','laboratory','other'])->default('hospital');
            $t->string('registration_no',100)->nullable()->unique(); $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->string('contact_person',120)->nullable(); $t->string('phone',40)->nullable(); $t->string('email')->nullable();
            $t->string('address')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); $t->index(['facility_type','is_active']);
        });
        Schema::create('healthcare_facility_api_tokens', function (Blueprint $t) {
            $t->id(); $t->foreignId('facility_id')->constrained('healthcare_facilities')->cascadeOnDelete();
            $t->string('name',120)->default('Integration token'); $t->char('token_hash',64)->unique();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_used_at')->nullable(); $t->timestamp('expires_at')->nullable();
            $t->boolean('is_active')->default(true); $t->timestamps();
            $t->index(['facility_id','is_active']);
        });
        Schema::table('prescriptions', function (Blueprint $t) {
            if (!Schema::hasColumn('prescriptions','healthcare_facility_id')) $t->foreignId('healthcare_facility_id')->nullable()->after('clinic_id')->constrained('healthcare_facilities')->nullOnDelete();
        });
        Schema::create('prescription_refills', function (Blueprint $t) {
            $t->id(); $t->string('request_no',50)->unique(); $t->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $t->foreignId('approved_prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('requested_quantity'); $t->enum('status',['pending','approved','rejected','dispensed'])->default('pending');
            $t->text('customer_note')->nullable(); $t->text('review_note')->nullable();
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('requested_at')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
            $t->index(['status','requested_at']);
        });
        Schema::create('prescription_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('prescription_id')->constrained()->cascadeOnDelete(); $t->string('event',60); $t->text('notes')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });

        Schema::create('insurance_eligibility_checks', function (Blueprint $t) {
            $t->id(); $t->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignId('provider_id')->constrained('insurance_providers')->restrictOnDelete();
            $t->foreignId('plan_id')->nullable()->constrained('insurance_plans')->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); $t->string('member_no',100);
            $t->enum('result',['eligible','ineligible','manual_review'])->default('manual_review'); $t->date('checked_on');
            $t->text('notes')->nullable(); $t->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
            $t->index(['branch_id','provider_id','member_no','checked_on'],'ins_elig_branch_provider_date_idx');
        });
        Schema::create('insurance_claim_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('claim_id')->constrained('insurance_claims')->cascadeOnDelete();
            $t->string('event',60); $t->string('external_reference',100)->nullable(); $t->decimal('amount',14,2)->nullable();
            $t->text('notes')->nullable(); $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });

        Schema::table('medicine_batches', function (Blueprint $t) {
            if (!Schema::hasColumn('medicine_batches','is_quarantined')) $t->boolean('is_quarantined')->default(false)->after('quantity');
        });
        Schema::table('medicines', function (Blueprint $t) {
            if (!Schema::hasColumn('medicines','requires_controlled_tracking')) $t->boolean('requires_controlled_tracking')->default(false)->after('requires_prescription');
        });
        Schema::create('controlled_medicine_register', function (Blueprint $t) {
            $t->id(); $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete(); $t->foreignId('batch_id')->nullable()->constrained('medicine_batches')->nullOnDelete();
            $t->enum('transaction_type',['purchase','dispense','return','adjustment','transfer_in','transfer_out']);
            $t->integer('quantity_change'); $t->unsignedInteger('balance_after')->nullable(); $t->string('reference_no',80)->nullable();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $t->text('reason')->nullable(); $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('recorded_at'); $t->timestamps();
            $t->index(['branch_id','medicine_id','recorded_at'],'cmr_branch_med_date_idx');
        });
        Schema::create('medicine_recalls', function (Blueprint $t) {
            $t->id(); $t->string('recall_no',50)->unique(); $t->string('source',120)->nullable();
            $t->enum('severity',['notice','warning','urgent'])->default('warning'); $t->text('reason');
            $t->enum('status',['open','in_progress','closed'])->default('open'); $t->date('issued_on');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('medicine_recall_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('recall_id')->constrained('medicine_recalls')->cascadeOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete(); $t->foreignId('batch_id')->nullable()->constrained('medicine_batches')->nullOnDelete();
            $t->unsignedInteger('affected_quantity')->default(0); $t->boolean('quarantine_applied')->default(false); $t->timestamps();
        });

        Schema::table('customers', function (Blueprint $t) {
            if (!Schema::hasColumn('customers','marketing_opt_in')) $t->boolean('marketing_opt_in')->default(false);
            if (!Schema::hasColumn('customers','marketing_opt_in_at')) $t->timestamp('marketing_opt_in_at')->nullable();
        });
        Schema::create('customer_campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name',160); $t->enum('campaign_type',['refill_reminder','loyalty','promotion','general'])->default('general');
            $t->text('message'); $t->enum('status',['draft','scheduled','active','completed','cancelled'])->default('draft');
            $t->dateTime('starts_at')->nullable(); $t->dateTime('ends_at')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('customer_campaign_deliveries', function (Blueprint $t) {
            $t->id(); $t->foreignId('campaign_id')->constrained('customer_campaigns')->cascadeOnDelete();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete(); $t->enum('channel',['whatsapp','sms','email','manual'])->default('manual');
            $t->enum('status',['queued','sent','failed','skipped'])->default('queued'); $t->string('provider_reference',120)->nullable(); $t->text('error_message')->nullable(); $t->timestamp('sent_at')->nullable(); $t->timestamps();
            $t->unique(['campaign_id','customer_id','channel'],'campaign_delivery_unique');
        });
        Schema::create('prescription_refill_reminders', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_id')->constrained()->cascadeOnDelete(); $t->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $t->foreignId('sale_id')->nullable()->constrained()->nullOnDelete(); $t->date('last_dispensed_on'); $t->date('remind_on');
            $t->enum('status',['pending','sent','dismissed','completed'])->default('pending'); $t->timestamp('sent_at')->nullable(); $t->timestamps();
            $t->index(['status','remind_on']);
        });
        Schema::create('mobile_push_subscriptions', function (Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete(); $t->text('endpoint'); $t->char('endpoint_hash',64);
            $t->text('public_key')->nullable(); $t->text('auth_token')->nullable(); $t->string('content_encoding',40)->nullable();
            $t->boolean('is_active')->default(true); $t->timestamp('last_used_at')->nullable(); $t->timestamps(); $t->unique(['user_id','endpoint_hash'],'push_user_endpoint_hash_unique');
        });
        Schema::create('analytics_forecast_runs', function (Blueprint $t) {
            $t->id(); $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); $t->date('period_start'); $t->date('period_end');
            $t->enum('forecast_type',['sales','demand','profit'])->default('demand'); $t->json('summary')->nullable();
            $t->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::create('analytics_forecast_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('run_id')->constrained('analytics_forecast_runs')->cascadeOnDelete();
            $t->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete(); $t->string('label',180)->nullable();
            $t->decimal('historical_daily_average',14,3)->default(0); $t->decimal('forecast_quantity',14,3)->default(0);
            $t->decimal('forecast_revenue',14,2)->default(0); $t->decimal('forecast_gross_profit',14,2)->default(0);
            $t->unsignedInteger('suggested_purchase_qty')->default(0); $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_forecast_items'); Schema::dropIfExists('analytics_forecast_runs');
        Schema::dropIfExists('mobile_push_subscriptions'); Schema::dropIfExists('prescription_refill_reminders');
        Schema::dropIfExists('customer_campaign_deliveries'); Schema::dropIfExists('customer_campaigns');
        Schema::table('customers', function (Blueprint $t) { if (Schema::hasColumn('customers','marketing_opt_in_at')) $t->dropColumn('marketing_opt_in_at'); if (Schema::hasColumn('customers','marketing_opt_in')) $t->dropColumn('marketing_opt_in'); });
        Schema::dropIfExists('medicine_recall_items'); Schema::dropIfExists('medicine_recalls'); Schema::dropIfExists('controlled_medicine_register');
        Schema::table('medicines', function (Blueprint $t) { if (Schema::hasColumn('medicines','requires_controlled_tracking')) $t->dropColumn('requires_controlled_tracking'); });
        Schema::table('medicine_batches', function (Blueprint $t) { if (Schema::hasColumn('medicine_batches','is_quarantined')) $t->dropColumn('is_quarantined'); });
        Schema::dropIfExists('insurance_claim_events'); Schema::dropIfExists('insurance_eligibility_checks');
        Schema::dropIfExists('prescription_events'); Schema::dropIfExists('prescription_refills');
        Schema::dropIfExists('healthcare_facility_api_tokens');
        Schema::table('prescriptions', function (Blueprint $t) { if (Schema::hasColumn('prescriptions','healthcare_facility_id')) $t->dropConstrainedForeignId('healthcare_facility_id'); });
        Schema::dropIfExists('healthcare_facilities');
        Schema::dropIfExists('warehouse_stock_count_items'); Schema::dropIfExists('warehouse_stock_counts'); Schema::dropIfExists('batch_location_stocks');
        Schema::dropIfExists('warehouse_locations'); Schema::dropIfExists('supplier_quotation_items'); Schema::dropIfExists('supplier_quotations');
    }
};
