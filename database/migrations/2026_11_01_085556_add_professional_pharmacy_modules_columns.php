<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id(); $table->string('name', 120); $table->string('code', 30)->unique();
            $table->string('phone', 40)->nullable(); $table->string('address')->nullable();
            $table->boolean('is_active')->default(true); $table->timestamps();
        });
        DB::table('branches')->insertOrIgnore(['id'=>1,'name'=>'Main Branch','code'=>'MAIN','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);

        foreach (['users'=>'id','medicine_batches'=>'id','purchases'=>'id','sales'=>'id','stock_movements'=>'id','purchase_returns'=>'id','sale_returns'=>'id','stock_adjustments'=>'id','payments'=>'id','cashier_sessions'=>'id'] as $table=>$after) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'branch_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($after) {
                    $blueprint->foreignId('branch_id')->nullable()->after($after)->constrained('branches')->nullOnDelete();
                });
            }
        }
        foreach (['medicine_batches','purchases','sales','stock_movements','purchase_returns','sale_returns','stock_adjustments','payments','cashier_sessions'] as $table) {
            if (Schema::hasTable($table)) DB::table($table)->whereNull('branch_id')->update(['branch_id'=>1]);
        }
        if (Schema::hasTable('users')) DB::table('users')->whereNull('branch_id')->update(['branch_id'=>1]);

        if (!Schema::hasColumn('medicines','requires_prescription')) {
            Schema::table('medicines', fn (Blueprint $t) => $t->boolean('requires_prescription')->default(false)->after('is_active'));
        }

        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id(); $t->string('order_no',40)->unique(); $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete(); $t->date('order_date'); $t->date('expected_date')->nullable();
            $t->enum('status',['draft','pending_approval','approved','rejected','received','cancelled'])->default('draft');
            $t->decimal('subtotal',12,2)->default(0); $t->decimal('discount',12,2)->default(0); $t->decimal('tax',12,2)->default(0); $t->decimal('total',12,2)->default(0);
            $t->text('notes')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable(); $t->timestamp('received_at')->nullable(); $t->timestamps();
            $t->index(['branch_id','status']);
        });
        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete(); $t->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('quantity'); $t->unsignedInteger('bonus_qty')->default(0); $t->decimal('purchase_price',12,2); $t->decimal('sale_price',12,2);
            $t->string('batch_no',100)->nullable(); $t->date('expiry_date')->nullable(); $t->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $t) {
            $t->id(); $t->string('prescription_no',40)->unique(); $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->string('doctor_name',150); $t->string('doctor_license',100)->nullable(); $t->date('prescription_date');
            $t->string('file_path')->nullable(); $t->enum('status',['pending','approved','rejected','dispensed'])->default('pending');
            $t->text('notes')->nullable(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps();
        });
        Schema::create('prescription_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('prescription_id')->constrained()->cascadeOnDelete(); $t->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $t->string('medicine_text',180)->nullable(); $t->decimal('quantity',10,2); $t->string('dosage',120)->nullable(); $t->string('duration',100)->nullable();
            $t->text('instructions')->nullable(); $t->timestamps();
        });
        if (!Schema::hasColumn('sales','prescription_id')) Schema::table('sales', fn(Blueprint $t) => $t->foreignId('prescription_id')->nullable()->after('customer_id')->constrained('prescriptions')->nullOnDelete());

        Schema::create('branch_stock_transfers', function (Blueprint $t) {
            $t->id(); $t->string('transfer_no',40)->unique(); $t->foreignId('from_branch_id')->constrained('branches')->restrictOnDelete();
            $t->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete(); $t->enum('status',['pending','approved','rejected','completed'])->default('pending');
            $t->text('notes')->nullable(); $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });
        Schema::create('branch_stock_transfer_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('transfer_id')->constrained('branch_stock_transfers')->cascadeOnDelete();
            $t->foreignId('medicine_id')->constrained()->restrictOnDelete(); $t->foreignId('source_batch_id')->constrained('medicine_batches')->restrictOnDelete();
            $t->foreignId('destination_batch_id')->nullable()->constrained('medicine_batches')->nullOnDelete(); $t->unsignedInteger('quantity'); $t->timestamps();
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id(); $t->foreignId('branch_id')->constrained()->restrictOnDelete(); $t->string('category',100); $t->string('description',255);
            $t->decimal('amount',12,2); $t->date('expense_date'); $t->string('payment_method',20)->default('cash'); $t->string('reference',100)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->enum('status',['pending','approved','rejected'])->default('approved'); $t->timestamps(); $t->index(['branch_id','expense_date']);
        });
        Schema::create('tax_profiles', function (Blueprint $t) {
            $t->id(); $t->string('name',100); $t->string('tax_code',40)->unique(); $t->decimal('rate',7,3)->default(0);
            $t->enum('calculation',['exclusive','inclusive'])->default('exclusive'); $t->boolean('is_active')->default(true); $t->boolean('is_default')->default(false); $t->timestamps();
        });
        if (!Schema::hasColumn('sales','tax_profile_id')) Schema::table('sales', fn(Blueprint $t) => $t->foreignId('tax_profile_id')->nullable()->after('prescription_id')->constrained('tax_profiles')->nullOnDelete());
        if (!Schema::hasColumn('sales','initial_paid')) Schema::table('sales', fn(Blueprint $t) => $t->decimal('initial_paid',14,2)->default(0)->after('paid'));
        DB::table('sales')->where('initial_paid',0)->update(['initial_paid'=>DB::raw('paid')]);

        Schema::create('accounting_accounts', function (Blueprint $t) {
            $t->id(); $t->string('code',30)->unique(); $t->string('name',150); $t->enum('type',['asset','liability','equity','income','expense']);
            $t->boolean('is_system')->default(false); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('journal_entries', function (Blueprint $t) {
            $t->id(); $t->string('entry_no',40)->unique(); $t->foreignId('branch_id')->constrained('branches')->restrictOnDelete(); $t->date('entry_date'); $t->string('narration',255); $t->string('source_type',100)->nullable();
            $t->unsignedBigInteger('source_id')->nullable(); $t->enum('status',['draft','posted','reversed'])->default('draft');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamp('posted_at')->nullable(); $t->timestamps();
            $t->unique(['source_type','source_id'],'journal_entries_source_unique');
        });
        Schema::create('journal_entry_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('journal_entry_id')->constrained()->cascadeOnDelete(); $t->foreignId('account_id')->constrained('accounting_accounts')->restrictOnDelete();
            $t->decimal('debit',14,2)->default(0); $t->decimal('credit',14,2)->default(0); $t->string('description',255)->nullable(); $t->timestamps();
        });

        Schema::create('whatsapp_notification_logs', function (Blueprint $t) {
            $t->id(); $t->string('event',80); $t->string('phone',30); $t->string('template_name',100)->nullable();
            $t->enum('status',['sent','failed'])->default('failed'); $t->json('provider_response')->nullable(); $t->date('notification_date'); $t->timestamps();
            $t->index(['event','notification_date']);
        });
        Schema::create('backup_runs', function (Blueprint $t) {
            $t->id(); $t->string('disk',40); $t->string('path',500)->nullable(); $t->unsignedBigInteger('size_bytes')->nullable();
            $t->enum('status',['success','failed'])->default('failed'); $t->text('message')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps();
        });

        // Seed a small, editable chart of accounts; no operational records are changed.
        $accounts = [
            ['1000','Cash and Bank','asset'],['1100','Inventory','asset'],['1200','Accounts Receivable','asset'],['1300','Input Tax Recoverable','asset'],['1400','Inter-branch Receivable','asset'],
            ['2000','Accounts Payable','liability'],['2100','Sales Tax Payable','liability'],['2200','Inter-branch Payable','liability'],['3000','Owner Capital','equity'],['4000','Medicine Sales','income'],
            ['4100','Sales Returns','income'],['4200','Inventory Adjustment Gain','income'],['5000','Cost of Goods Sold','expense'],['6000','Operating Expenses','expense'],['6100','Inventory Write-off','expense'],
        ];
        foreach ($accounts as [$code,$name,$type]) DB::table('accounting_accounts')->insertOrIgnore(['code'=>$code,'name'=>$name,'type'=>$type,'is_system'=>true,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs'); Schema::dropIfExists('whatsapp_notification_logs'); Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries'); Schema::dropIfExists('accounting_accounts');
        Schema::table('sales', function(Blueprint $t){ if(Schema::hasColumn('sales','initial_paid')) $t->dropColumn('initial_paid'); if(Schema::hasColumn('sales','tax_profile_id')) $t->dropConstrainedForeignId('tax_profile_id'); if(Schema::hasColumn('sales','prescription_id')) $t->dropConstrainedForeignId('prescription_id'); });
        Schema::table('medicines', function(Blueprint $t){ if(Schema::hasColumn('medicines','requires_prescription')) $t->dropColumn('requires_prescription'); });
        Schema::dropIfExists('tax_profiles'); Schema::dropIfExists('expenses'); Schema::dropIfExists('branch_stock_transfer_items'); Schema::dropIfExists('branch_stock_transfers');
        Schema::dropIfExists('prescription_items'); Schema::dropIfExists('prescriptions'); Schema::dropIfExists('purchase_order_items'); Schema::dropIfExists('purchase_orders');
        foreach (['stock_movements','sales','purchases','medicine_batches','users','purchase_returns','sale_returns','stock_adjustments','payments','cashier_sessions'] as $table) if(Schema::hasColumn($table,'branch_id')) Schema::table($table,fn(Blueprint $t)=>$t->dropConstrainedForeignId('branch_id'));
        Schema::dropIfExists('branches');
    }
};
