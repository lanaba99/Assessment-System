<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_blueprints', function (Blueprint $table): void {
            $table->dropColumn([
                'difficulty_distribution_easy_count',
                'difficulty_distribution_medium_count',
                'difficulty_distribution_hard_count',
            ]);
        });

        Schema::table('exam_blueprints', function (Blueprint $table): void {
            $table->uuid('section_id')->nullable()->after('competency_id');
            $table->json('bloom_distribution')->nullable()->after('max_weight_percentage');
            $table->decimal('target_difficulty', 4, 3)->default(0.600)->after('bloom_distribution');
            $table->decimal('min_discrimination', 4, 3)->default(0.200)->after('target_difficulty');
            $table->string('resolution_strategy')->default('stratified')->after('min_discrimination');

            $table->foreign('section_id')
                ->references('section_id')
                ->on('exam_sections')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->index('section_id');
        });

        Schema::create('exam_session_items', function (Blueprint $table): void {
            $table->uuid('session_item_id')->primary();
            $table->uuid('session_id');
            $table->uuid('section_id');
            $table->uuid('question_version_id');

            $table->unsignedInteger('sequence_number');
            $table->string('item_state')->default('pending');

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('answered_at')->nullable();

            $table->boolean('is_flagged')->default(false);

            $table->timestamp('version_lock')->nullable();
            $table->timestamps();

            $table->foreign('session_id')
                ->references('session_id')
                ->on('exam_sessions')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('section_id')
                ->references('section_id')
                ->on('exam_sections')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('question_version_id')
                ->references('version_id')
                ->on('question_versions')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->unique(['session_id', 'sequence_number']);
            $table->index('item_state');
            $table->index('is_flagged');
        });

        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->timestamp('last_heartbeat_at')->nullable()->after('session_ended_at');
            $table->json('heartbeat_metadata')->nullable()->after('last_heartbeat_at');

            $table->index('last_heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table): void {
            $table->dropIndex(['last_heartbeat_at']);
            $table->dropColumn(['last_heartbeat_at', 'heartbeat_metadata']);
        });

        Schema::dropIfExists('exam_session_items');

        Schema::table('exam_blueprints', function (Blueprint $table): void {
            $table->dropForeign(['section_id']);
            $table->dropIndex(['section_id']);
            $table->dropColumn([
                'section_id',
                'bloom_distribution',
                'target_difficulty',
                'min_discrimination',
                'resolution_strategy',
            ]);
        });

        Schema::table('exam_blueprints', function (Blueprint $table): void {
            $table->unsignedInteger('difficulty_distribution_easy_count')->default(0);
            $table->unsignedInteger('difficulty_distribution_medium_count')->default(0);
            $table->unsignedInteger('difficulty_distribution_hard_count')->default(0);
        });
    }
};
