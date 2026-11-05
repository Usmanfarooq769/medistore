<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('online_orders') && ! Schema::hasColumn('online_orders', 'idempotency_key')) {
            Schema::table('online_orders', function (Blueprint $table) {
                $table->string('idempotency_key', 100)->nullable()->unique('online_orders_idempotency_key_unique');
                $table->char('idempotency_payload_hash', 64)->nullable();
            });
        }
        if (Schema::hasTable('customer_return_requests') && ! Schema::hasIndex('customer_return_requests', 'customer_return_requests_sale_return_id_unique')) {
            Schema::table('customer_return_requests', function (Blueprint $table) {
                $table->unique('sale_return_id', 'customer_return_requests_sale_return_id_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_return_requests') && Schema::hasIndex('customer_return_requests', 'customer_return_requests_sale_return_id_unique')) {
            Schema::table('customer_return_requests', fn (Blueprint $table) => $table->dropUnique('customer_return_requests_sale_return_id_unique'));
        }
        if (Schema::hasTable('online_orders') && Schema::hasColumn('online_orders', 'idempotency_key')) {
            Schema::table('online_orders', function (Blueprint $table) {
                $table->dropUnique('online_orders_idempotency_key_unique');
                $table->dropColumn(['idempotency_key', 'idempotency_payload_hash']);
            });
        }
    }
};
