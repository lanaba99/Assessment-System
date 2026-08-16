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
        // Guarded: in production this runs against the landlord's own
        // database, where 'jobs' never pre-exists. The guard only matters
        // for test harnesses (e.g. RolePermissionContractTest) that load
        // both landlord AND tenant migrations into a single shared SQLite
        // connection — the tenant migrations also create a 'jobs' table
        // (create_tenant_queues_table.php) for each tenant's own queue,
        // which is correct in production (separate DB per tenant) but
        // collides here since both sets land in the same schema.
        if (Schema::hasTable('jobs')) {
            return;
        }

        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};