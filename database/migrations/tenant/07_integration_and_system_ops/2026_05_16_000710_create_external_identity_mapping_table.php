<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_identity_mapping', function (Blueprint $table) {
            $table->uuid('mapping_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id');

            $table->string('external_provider_name');
            $table->string('external_user_id');

            $table->json('sync_metadata')->nullable();

            $table->timestamp('last_synced_at')->nullable();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['external_provider_name', 'external_user_id'], 'ext_identity_provider_user_unq');
            $table->index('tenant_id');
            $table->index('last_synced_at');
            $table->index(['user_id', 'external_provider_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_identity_mapping');
    }
};
