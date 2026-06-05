<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Priority #2 — Soft deletes for the QuestionBank aggregate.
 *
 * Hard deletes cascaded through question_versions → question_options →
 * question_psychometrics and would orphan exam_sessions / question_responses
 * that pin a specific version. Adding `deleted_at` lets us retire a question
 * (or category) without destroying the historical record an exam result
 * depends on. Child rows (options, psychometrics, tags) are intentionally NOT
 * soft-deleted: they live and die with their immutable version row.
 */
return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $tables = [
        'questions',
        'question_versions',
        'categories',
        'media_assets',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            // Defensive + idempotent: only touch tables that exist (a partial
            // schema such as the test harness may not build every table), and
            // skip any that already carry the column (a half-applied prior run
            // — MySQL auto-commits each ALTER and has no transactional DDL to
            // roll back).
            if (! Schema::hasTable($name) || Schema::hasColumn($name, 'deleted_at')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table): void {
                $table->softDeletes();
                $table->index('deleted_at');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (! Schema::hasTable($name) || ! Schema::hasColumn($name, 'deleted_at')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table): void {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            });
        }
    }
};
