<?php

declare(strict_types=1);

namespace Tests\Feature\QuestionBank;

use App\Domains\QuestionBank\Models\QuestionBank;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Identity\UsesIdentitySchema;

trait UsesQuestionBankSchema
{
    use UsesIdentitySchema;

    protected function bootQuestionBankSchema(): void
    {
        $this->bootIdentitySchema();
        $this->migrateQuestionBankTables();
    }

    private function migrateQuestionBankTables(): void
    {
        $files = [
            '2026_05_16_000140_create_categories_table.php',
            '2026_05_16_000150_create_questions_table.php',
            '2026_05_16_000160_create_question_versions_table.php',
            '2026_05_16_000180_create_question_options_table.php',
            '2026_05_19_000010_create_question_psychometrics_table.php',
        ];

        $basePath = database_path('migrations/tenant/02_assessment_and_exams');
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

    protected function createCategory(
        string $tenantId,
        string $title,
        ?string $parentId = null,
        array $overrides = [],
    ): QuestionBank {
        return QuestionBank::query()->create(array_merge([
            'category_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'parent_category_id' => $parentId,
            'category_name' => $title,
            'category_code' => 'CAT-' . Str::upper(Str::random(8)),
            'display_order' => 0,
            'hierarchy_level' => $parentId === null ? 0 : 1,
            'is_locked' => false,
            'is_active' => true,
        ], $overrides));
    }
}
