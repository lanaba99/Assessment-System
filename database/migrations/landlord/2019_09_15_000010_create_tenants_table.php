<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('subdomain')->unique();
            $table->string('organization_name');
            $table->string('organization_type')->nullable();
            $table->string('primary_contact_email');
            $table->string('primary_contact_phone')->nullable();

            $table->json('deployment_config')->nullable();
            $table->string('deployment_mode')->nullable();
            $table->string('data_residency_location')->nullable();

            $table->unsignedInteger('max_concurrent_users')->nullable();
            $table->unsignedInteger('max_storage_quota_mb')->nullable();

            $table->json('feature_flags')->nullable();
            $table->string('status')->default('active');
            $table->json('security_policies')->nullable();

            $table->dateTime('contract_start_date')->nullable();
            $table->dateTime('contract_end_date')->nullable();

            $table->timestamps();
            $table->timestamp('suspended_at')->nullable();

            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
