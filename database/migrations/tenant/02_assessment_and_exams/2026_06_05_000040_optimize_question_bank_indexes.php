<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Priority #7 — index hardening.
 *
 *  1. Drop the redundant `tenant_id` indexes. Under per-database tenancy every
 *     row in a tenant DB shares the same tenant_id, so the index is pure write
 *     overhead and never narrows a scan.
 *  2. Add a composite index on questions(category_id, updated_at) — the shape
 *     of the question list query (filter by category, sort by recency).
 *  3. Add a standalone index on question_competency_weights(competency_id) for
 *     the "all questions for competency X" lookup the Competency module needs;
 *     the existing unique(question_id, competency_id) is leftmost-prefixed on
 *     question_id and can't serve it.
 *
 * Every operation is guarded so the migration is safe to re-run against a
 * partially-applied or drifted tenant schema.
 */
return new class extends Migration
{
    /**
     * table => default Laravel index name for its tenant_id index.
     *
     * @var array<string, string>
     */
    private array $tenantIdIndexes = [
        'categories' => 'categories_tenant_id_index',
        'questions' => 'questions_tenant_id_index',
        'question_tags' => 'question_tags_tenant_id_index',
        'media_assets' => 'media_assets_tenant_id_index',
        'question_psychometrics' => 'question_psychometrics_tenant_id_index',
    ];

    public function up(): void
    {
        foreach ($this->tenantIdIndexes as $table => $index) {
            if (Schema::hasTable($table) && $this->hasIndex($table, $index)) {
                Schema::table($table, function (Blueprint $blueprint) use ($index): void {
                    $blueprint->dropIndex($index);
                });
            }
        }

        if (Schema::hasTable('questions') && ! $this->hasIndex('questions', 'questions_category_id_updated_at_index')) {
            Schema::table('questions', function (Blueprint $table): void {
                $table->index(['category_id', 'updated_at']);
            });
        }

        if (Schema::hasTable('question_competency_weights')
            && ! $this->hasIndex('question_competency_weights', 'question_competency_weights_competency_id_index')) {
            Schema::table('question_competency_weights', function (Blueprint $table): void {
                $table->index('competency_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('question_competency_weights')
            && $this->hasIndex('question_competency_weights', 'question_competency_weights_competency_id_index')) {
            Schema::table('question_competency_weights', function (Blueprint $table): void {
                $table->dropIndex('question_competency_weights_competency_id_index');
            });
        }

        if (Schema::hasTable('questions') && $this->hasIndex('questions', 'questions_category_id_updated_at_index')) {
            Schema::table('questions', function (Blueprint $table): void {
                $table->dropIndex('questions_category_id_updated_at_index');
            });
        }

        foreach ($this->tenantIdIndexes as $table => $index) {
            if (Schema::hasTable($table) && ! $this->hasIndex($table, $index)) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->index('tenant_id');
                });
            }
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $existing) {
            if (($existing['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
