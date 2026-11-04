<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medication_safety_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('incident_number', 40)->unique()->nullable();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('medicine_batches')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('incident_type', 40);
            $table->string('severity', 20)->default('low');
            $table->string('status', 25)->default('reported');
            $table->dateTime('occurred_at');
            $table->text('description');
            $table->text('immediate_action')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('clinical_outcome')->nullable();
            $table->text('closure_summary')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status', 'occurred_at']);
            $table->index(['incident_type', 'severity']);
        });

        Schema::create('quality_improvement_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('medication_safety_incidents')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type', 20)->default('corrective');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'due_date']);
        });

        Schema::create('controlled_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_code', 60)->unique();
            $table->string('title');
            $table->string('category', 60)->default('standard_operating_procedure');
            $table->unsignedInteger('current_version')->default(1);
            $table->string('status', 25)->default('draft');
            $table->date('effective_date')->nullable();
            $table->date('review_due_date')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'review_due_date']);
        });

        Schema::create('controlled_document_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('controlled_documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_path');
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'version']);
        });

        Schema::create('compliance_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_number', 40)->unique()->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('audit_scope', 100)->nullable();
            $table->date('audit_date');
            $table->string('status', 20)->default('in_progress');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'status', 'audit_date']);
        });

        Schema::create('compliance_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('compliance_audits')->cascadeOnDelete();
            $table->string('item_code', 40)->nullable();
            $table->string('requirement');
            $table->string('result', 25)->default('pending');
            $table->text('evidence_notes')->nullable();
            $table->text('corrective_action')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['audit_id', 'result']);
        });

        Schema::create('product_roadmap_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('milestone_number')->unique();
            $table->string('milestone_code', 40)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('planned');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->date('target_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_roadmap_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained('product_roadmap_milestones')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('planned');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['milestone_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_roadmap_tasks');
        Schema::dropIfExists('product_roadmap_milestones');
        Schema::dropIfExists('compliance_audit_items');
        Schema::dropIfExists('compliance_audits');
        Schema::dropIfExists('controlled_document_revisions');
        Schema::dropIfExists('controlled_documents');
        Schema::dropIfExists('quality_improvement_actions');
        Schema::dropIfExists('medication_safety_incidents');
    }
};
