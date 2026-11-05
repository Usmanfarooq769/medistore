<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('online_orders') && ! Schema::hasColumn('online_orders', 'payment_method')) {
            Schema::table('online_orders', function (Blueprint $table) {
                $table->string('payment_method', 30)->default('cash_on_delivery');
                $table->string('payment_status', 20)->default('pending');
                $table->string('payment_reference', 150)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('online_orders') && Schema::hasColumn('online_orders', 'payment_method')) {
            Schema::table('online_orders', function (Blueprint $table) {
                $table->dropColumn(['payment_method', 'payment_status', 'payment_reference']);
            });
        }
    }
};
