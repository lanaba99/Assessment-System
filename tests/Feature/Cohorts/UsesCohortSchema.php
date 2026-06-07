<?php

declare(strict_types=1);

namespace Tests\Feature\Cohorts;

use App\Domains\Cohorts\Models\Cohort;
use App\Domains\Cohorts\Models\CohortMember;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Identity\UsesIdentitySchema;

/**
 * Builds the cohort tables on top of the identity schema for feature tests.
 *
 * We define the schema inline rather than loading the production migration files
 * because the cohorts migration also alters the `exam_enrollments` table (adding
 * a FK back to cohorts). That table is not part of this focused harness, so
 * loading the file directly would throw. The inline definition mirrors the
 * migration exactly — keep them in lockstep manually if columns change.
 *
 * Tables built here:
 *   cohorts → cohort_members
 *
 * Tables intentionally excluded:
 * - exam_enrollments  Requires the full ExamSession + ExamEngine stack.
 *   The cohort→enrollment FK is an ExamSession concern and is tested there.
 */
trait UsesCohortSchema
{
    use UsesIdentitySchema;

    protected function bootCohortSchema(): void
    {
        $this->bootIdentitySchema();
        $this->migrateCohortTables();
    }

    private function migrateCohortTables(): void
    {
        $connection = (string) config('database.default');

        if ($connection !== 'sqlite') {
            Schema::connection($connection)->disableForeignKeyConstraints();

            foreach (['cohort_members', 'cohorts'] as $table) {
                try {
                    Schema::connection($connection)->drop($table);
                } catch (\Throwable) {
                    // Table may not exist on a fresh run.
                }
            }

            Schema::connection($connection)->enableForeignKeyConstraints();
        }

        Schema::create('cohorts', function (Blueprint $table): void {
            $table->uuid('cohort_id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('parent_cohort_id')->nullable();
            $table->uuid('created_by_user_id');

            $table->string('cohort_name');
            $table->string('cohort_code')->unique();
            $table->string('cohort_type');
            $table->text('cohort_description')->nullable();

            $table->unsignedInteger('hierarchy_level')->default(0);
            $table->json('cohort_attributes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('parent_cohort_id')
                ->references('cohort_id')
                ->on('cohorts')
                ->onUpdate('cascade')
                ->onDelete('set null');

            $table->foreign('created_by_user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });

        Schema::create('cohort_members', function (Blueprint $table): void {
            $table->uuid('member_id')->primary();
            $table->uuid('cohort_id');
            $table->uuid('user_id');
            $table->uuid('tenant_id');

            $table->string('membership_role')->default('member');
            $table->timestamp('added_at')->useCurrent();
            $table->timestamp('removed_at')->nullable();
            $table->boolean('is_active_member')->default(true);

            $table->foreign('cohort_id')
                ->references('cohort_id')
                ->on('cohorts')
                ->onUpdate('cascade')
                ->onDelete('restrict');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->unique(['cohort_id', 'user_id']);
        });
    }

    /**
     * Create a Cohort record directly via forceCreate, bypassing the HTTP stack.
     * Use this for test setup only; business-logic assertions must go through
     * the API endpoints.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createCohort(string $tenantId, string $createdByUserId, array $overrides = []): Cohort
    {
        return Cohort::query()->forceCreate(array_merge([
            'cohort_id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdByUserId,
            'cohort_name' => 'Test Cohort ' . Str::random(6),
            'cohort_code' => 'COH-' . strtoupper(Str::random(8)),
            'cohort_type' => 'team',
            'cohort_description' => null,
            'hierarchy_level' => 0,
            'cohort_attributes' => null,
            'is_active' => true,
        ], $overrides));
    }

    /**
     * Create a CohortMember record directly via forceCreate.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createCohortMember(string $tenantId, string $cohortId, string $userId, array $overrides = []): CohortMember
    {
        return CohortMember::query()->forceCreate(array_merge([
            'member_id' => (string) Str::uuid(),
            'cohort_id' => $cohortId,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'membership_role' => 'member',
            'added_at' => now(),
            'removed_at' => null,
            'is_active_member' => true,
        ], $overrides));
    }
}
