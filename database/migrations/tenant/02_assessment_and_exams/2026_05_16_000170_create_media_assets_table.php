<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->uuid('asset_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('question_id')->nullable();
            $table->uuid('uploaded_by_user_id');

            $table->string('asset_type');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_url')->nullable();

            $table->unsignedBigInteger('file_size_bytes')->default(0);

            $table->string('storage_location')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('virus_scan_status')->default('pending');

            $table->json('asset_metadata')->nullable();

            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('scanned_at')->nullable();

            $table->foreign('question_id')
                ->references('question_id')
                ->on('questions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('uploaded_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('asset_type');
            $table->index('virus_scan_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
