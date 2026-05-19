<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('question_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('category_id');
            $table->uuid('created_by_user_id');
            $table->uuid('current_version_id')->nullable();

            $table->string('question_title');
            $table->string('question_type');
            $table->unsignedTinyInteger('difficulty_level')->default(1);
            $table->unsignedTinyInteger('cognitive_level')->default(1);

            $table->boolean('is_randomizable')->default(true);
            $table->boolean('requires_media_attachment')->default(false);
            $table->boolean('is_deprecated')->default(false);
            $table->boolean('is_archived')->default(false);

            $table->unsignedBigInteger('total_usage_count')->default(0);

            $table->json('question_metadata')->nullable();

            $table->timestamps();
            $table->timestamp('archived_at')->nullable();

            $table->foreign('category_id')
                ->references('category_id')
                ->on('categories')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->index('tenant_id');
            $table->index('question_type');
            $table->index('difficulty_level');
            $table->index('is_archived');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
