<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('tenant_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('slug', 100)->unique();
            $table->string('status', 20)->default('active'); // active, suspended, provisioning
            $table->string('database_driver', 20)->default('mysql');
            $table->string('database_host', 190);
            $table->unsignedSmallInteger('database_port')->default(3306);
            $table->string('database_name', 100)->unique();
            $table->string('database_username', 100);
            $table->text('database_password_encrypted')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'slug']);
        });

        Schema::connection('central')->create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_company_id')->constrained('tenant_companies')->cascadeOnDelete();
            $table->string('domain', 253)->unique();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
            $table->index(['tenant_company_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenant_domains');
        Schema::connection('central')->dropIfExists('tenant_companies');
    }
};
