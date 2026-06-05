<?php

declare(strict_types=1);

namespace Tests\Feature\Competency;

use App\Domains\Competency\Models\Competency;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Identity\UsesIdentitySchema;

/**
 * Builds the competency tables on top of the identity schema for feature tests
 * (mirrors UsesQuestionBankSchema). The create_competencies migration adds
 * cross-table FKs to exam_blueprints/competency_scores guarded by hasTable, so
 * it runs cleanly here even though those tables are not built.
 */
trait UsesCompetencySchema
{
    use UsesIdentitySchema;

    protected function bootCompetencySchema(): void
    {
        $this->bootIdentitySchema();
        $this->migrateCompetencyTables();
    }

    private function migrateCompetencyTables(): void
    {
        // Keep in lockstep with production. New migrations that alter the
        // competencies table MUST be appended here or the test schema drifts.
        $files = [
            '2026_05_16_000360_create_competencies_table.php',
            '2026_06_05_000050_add_tree_to_competencies.php',
        ];

        $basePath = database_path('migrations/tenant/03_ksa_and_competencies');
        $connection = (string) config('database.default');

        if ($connection !== 'sqlite') {
            Schema::connection($connection)->disableForeignKeyConstraints();

            foreach (array_reverse($files) as $file) {
                $migration = require $basePath . '/' . $file;

                try {
                    $migration->down();
                } catch (\Throwable) {
                    // Fresh databases have nothing to roll back.
                }
            }

            Schema::connection($connection)->enableForeignKeyConstraints();
        }

        foreach ($files as $file) {
            $migration = require $basePath . '/' . $file;
            $migration->up();
        }
    }

    protected function createCompetency(
        string $tenantId,
        string $createdByUserId,
        string $name,
        ?string $parentId = null,
        array $overrides = [],
    ): Competency {
        return Competency::query()->forceCreate(array_merge([
            'competency_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdByUserId,
            'parent_competency_id' => $parentId,
            'competency_name' => $name,
            'competency_type' => Competency::TYPE_KNOWLEDGE,
            'description' => null,
            'hierarchy_level' => $parentId === null ? 0 : 1,
            'is_active' => true,
        ], $overrides));
    }
}
