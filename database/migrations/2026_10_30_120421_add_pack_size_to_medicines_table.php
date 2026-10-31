<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        if (! Schema::hasColumn('medicines', 'pack_size')) {
            Schema::table('medicines', function (Blueprint $table) {
                $table->unsignedInteger('pack_size')->default(1)->after('unit');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('medicines', 'pack_size')) {
            Schema::table('medicines', fn (Blueprint $table) => $table->dropColumn('pack_size'));
        }
    }
};
