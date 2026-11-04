<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_portal_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('password');
            $t->string('status', 20)->default('active');
            $t->timestamp('last_login_at')->nullable();
            $t->timestamps();
        });
        Schema::create('customer_portal_invitations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('expires_at');
            $t->timestamp('redeemed_at')->nullable();
            $t->timestamps();
            $t->index(['customer_id', 'expires_at']);
        });
        Schema::create('customer_portal_tokens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('account_id')->constrained('customer_portal_accounts')->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at');
            $t->timestamps();
        });

        Schema::create('partner_api_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('client_key', 64)->unique();
            $t->string('secret_hash', 255);
            $t->json('scopes');
            $t->json('branch_ids')->nullable();
            $t->unsignedInteger('rate_limit_per_minute')->default(60);
            $t->string('status', 20)->default('active');
            $t->timestamp('last_used_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'created_at']);
        });
        Schema::create('partner_webhook_endpoints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->constrained('partner_api_clients')->cascadeOnDelete();
            $t->string('url', 1000);
            $t->json('events');
            $t->text('signing_secret');
            $t->string('status', 20)->default('active');
            $t->timestamps();
        });
        Schema::create('partner_webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('endpoint_id')->constrained('partner_webhook_endpoints')->cascadeOnDelete();
            $t->string('event_name', 100);
            $t->json('payload');
            $t->string('status', 20)->default('pending');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedSmallInteger('response_code')->nullable();
            $t->text('response_excerpt')->nullable();
            $t->timestamp('next_attempt_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'next_attempt_at']);
        });
        if (Schema::hasTable('online_orders') && ! Schema::hasColumn('online_orders', 'partner_api_client_id')) {
            Schema::table('online_orders', function (Blueprint $t) {
                $t->foreignId('partner_api_client_id')->nullable()->after('customer_id')->constrained('partner_api_clients')->nullOnDelete();
            });
        }

        Schema::create('partner_api_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('partner_id')->nullable()->constrained('partner_api_clients')->nullOnDelete();
            $t->string('method', 10);
            $t->string('path', 500);
            $t->unsignedSmallInteger('response_code')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['partner_id', 'created_at']);
        });

        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 60)->default('system');
            $t->string('title', 180);
            $t->text('body')->nullable();
            $t->json('data')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('subscription_plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->decimal('monthly_price', 12, 2)->default(0);
            $t->decimal('annual_price', 12, 2)->default(0);
            $t->unsignedSmallInteger('branch_limit')->default(1);
            $t->unsignedInteger('user_limit')->default(5);
            $t->json('features')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('branch_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->restrictOnDelete();
            $t->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();
            $t->string('billing_cycle', 20)->default('monthly');
            $t->string('status', 20)->default('trial');
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->date('trial_ends_on')->nullable();
            $t->string('external_reference', 150)->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['branch_id', 'status', 'ends_on']);
        });
        Schema::create('subscription_invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')->constrained('branch_subscriptions')->cascadeOnDelete();
            $t->string('invoice_number', 50)->unique();
            $t->date('invoice_date');
            $t->date('due_date');
            $t->decimal('amount', 12, 2);
            $t->string('status', 20)->default('unpaid');
            $t->string('payment_reference', 150)->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'due_date']);
        });

        Schema::create('system_health_snapshots', function (Blueprint $t) {
            $t->id();
            $t->string('overall_status', 20);
            $t->json('checks');
            $t->unsignedBigInteger('free_disk_bytes')->nullable();
            $t->unsignedInteger('failed_jobs')->default(0);
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('checked_at');
            $t->timestamps();
            $t->index(['overall_status', 'checked_at']);
        });
        Schema::create('deployment_releases', function (Blueprint $t) {
            $t->id();
            $t->string('version', 60)->unique();
            $t->string('environment', 30)->default('production');
            $t->text('release_notes')->nullable();
            $t->string('status', 20)->default('prepared');
            $t->foreignId('deployed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('deployed_at')->nullable();
            $t->timestamps();
            $t->index(['environment', 'status', 'deployed_at']);
        });
        Schema::create('disaster_recovery_drills', function (Blueprint $t) {
            $t->id();
            $t->string('drill_reference', 50)->unique();
            $t->foreignId('backup_run_id')->nullable()->constrained('backup_runs')->nullOnDelete();
            $t->string('environment', 30)->default('staging');
            $t->string('result', 20)->default('planned');
            $t->dateTime('started_at')->nullable();
            $t->dateTime('completed_at')->nullable();
            $t->unsignedInteger('recovery_minutes')->nullable();
            $t->text('findings')->nullable();
            $t->text('follow_up_actions')->nullable();
            $t->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['result', 'completed_at']);
        });
        Schema::create('security_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('event_type', 80);
            $t->string('email', 190)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['event_type', 'created_at']);
            $t->index(['ip_address', 'created_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('online_orders') && Schema::hasColumn('online_orders', 'partner_api_client_id')) {
            Schema::table('online_orders', fn (Blueprint $t) => $t->dropConstrainedForeignId('partner_api_client_id'));
        }
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('disaster_recovery_drills');
        Schema::dropIfExists('deployment_releases');
        Schema::dropIfExists('system_health_snapshots');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('branch_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('partner_api_logs');
        Schema::dropIfExists('partner_webhook_deliveries');
        Schema::dropIfExists('partner_webhook_endpoints');
        Schema::dropIfExists('partner_api_clients');
        Schema::dropIfExists('customer_portal_tokens');
        Schema::dropIfExists('customer_portal_invitations');
        Schema::dropIfExists('customer_portal_accounts');
    }
};
