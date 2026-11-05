<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('online_order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('online_order_items', 'sale_unit')) {
                $table->string('sale_unit', 10)->default('unit')->after('quantity');
            }
            if (! Schema::hasColumn('online_order_items', 'sale_quantity')) {
                $table->unsignedInteger('sale_quantity')->default(1)->after('sale_unit');
            }
            if (! Schema::hasColumn('online_order_items', 'pack_size')) {
                $table->unsignedInteger('pack_size')->default(1)->after('sale_quantity');
            }
        });

        // Existing online orders stored individual inventory units. Preserve their quantities.
        DB::table('online_order_items')->where('sale_quantity', 1)->update(['sale_quantity' => DB::raw('quantity')]);
    }

    public function down(): void
    {
        Schema::table('online_order_items', function (Blueprint $table) {
            foreach (['pack_size', 'sale_quantity', 'sale_unit'] as $column) {
                if (Schema::hasColumn('online_order_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
