<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Professional upgrade:
 *  - richer medicine / customer master data (dosage form, storage, allergies, CNIC ...)
 *  - per-user permissions + hashed manager PIN
 *  - FBR invoice fields on sales + override approver
 *  - drug interaction reference table (pharmacist-editable)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $t) {
            if (! Schema::hasColumn('medicines', 'dosage_form'))       $t->string('dosage_form', 40)->nullable()->after('strength');
            if (! Schema::hasColumn('medicines', 'storage_condition')) $t->string('storage_condition', 30)->nullable()->after('rack');
            if (! Schema::hasColumn('medicines', 'drug_class'))        $t->string('drug_class', 30)->nullable()->after('storage_condition');
            if (! Schema::hasColumn('medicines', 'hs_code'))           $t->string('hs_code', 20)->nullable()->after('drug_class');
        });

        Schema::table('customers', function (Blueprint $t) {
            if (! Schema::hasColumn('customers', 'allergies'))           $t->text('allergies')->nullable();
            if (! Schema::hasColumn('customers', 'chronic_conditions'))  $t->text('chronic_conditions')->nullable();
            if (! Schema::hasColumn('customers', 'date_of_birth'))       $t->date('date_of_birth')->nullable();
            if (! Schema::hasColumn('customers', 'gender'))              $t->string('gender', 10)->nullable();
            if (! Schema::hasColumn('customers', 'cnic'))                $t->string('cnic', 20)->nullable();
            if (! Schema::hasColumn('customers', 'email'))               $t->string('email', 150)->nullable();
        });

        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'manager_pin'))  $t->string('manager_pin')->nullable();   // bcrypt hash
            if (! Schema::hasColumn('users', 'permissions'))  $t->json('permissions')->nullable();    // per-user overrides {key: bool}
        });

        Schema::table('sales', function (Blueprint $t) {
            if (! Schema::hasColumn('sales', 'fbr_invoice_no')) $t->string('fbr_invoice_no', 60)->nullable()->index();
            if (! Schema::hasColumn('sales', 'fbr_status'))     $t->string('fbr_status', 20)->default('not_required');
            if (! Schema::hasColumn('sales', 'fbr_error'))      $t->string('fbr_error', 255)->nullable();
            if (! Schema::hasColumn('sales', 'fbr_synced_at'))  $t->timestamp('fbr_synced_at')->nullable();
            if (! Schema::hasColumn('sales', 'approved_by'))    $t->unsignedBigInteger('approved_by')->nullable(); // manager who approved an override
            if (! Schema::hasColumn('sales', 'safety_override_note')) $t->string('safety_override_note', 255)->nullable();
        });

        if (! Schema::hasTable('drug_interactions')) {
            Schema::create('drug_interactions', function (Blueprint $t) {
                $t->id();
                $t->string('ingredient_a', 120);
                $t->string('ingredient_b', 120);
                $t->enum('severity', ['minor', 'moderate', 'major', 'contraindicated'])->default('moderate');
                $t->string('effect', 255);
                $t->string('management', 255)->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['ingredient_a', 'ingredient_b']);
                $t->index('ingredient_b');
            });
        }

        (new \Database\Seeders\DrugInteractionSeeder())->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_interactions');
        Schema::table('sales', function (Blueprint $t) {
            foreach (['fbr_invoice_no', 'fbr_status', 'fbr_error', 'fbr_synced_at', 'approved_by', 'safety_override_note'] as $c) {
                if (Schema::hasColumn('sales', $c)) $t->dropColumn($c);
            }
        });
        Schema::table('users', function (Blueprint $t) {
            foreach (['manager_pin', 'permissions'] as $c) if (Schema::hasColumn('users', $c)) $t->dropColumn($c);
        });
        Schema::table('customers', function (Blueprint $t) {
            foreach (['allergies', 'chronic_conditions', 'date_of_birth', 'gender', 'cnic', 'email'] as $c) if (Schema::hasColumn('customers', $c)) $t->dropColumn($c);
        });
        Schema::table('medicines', function (Blueprint $t) {
            foreach (['dosage_form', 'storage_condition', 'drug_class', 'hs_code'] as $c) if (Schema::hasColumn('medicines', $c)) $t->dropColumn($c);
        });
    }
};
