<?php

namespace Tests\Feature\Identity;

use Tests\TestCase;
use Database\Seeders\TenantMasterSeeder;
use Illuminate\Support\Facades\DB;
use App\Domains\Identity\Enums\RoleName;

class RolePermissionContractTest extends TestCase
{
    // 1. remove the default RefreshDatabase trait to avoid random truncated builds

    protected function setUp(): void
    {
        parent::setUp();

        // 2. we set up the temporary database (SQLite) with precise migration paths

        $this->artisan('migrate:fresh', [
            '--path' => [
                'database/migrations',        // main default migrations (landlord + tenant)
                'database/migrations/tenant',
                'database/migrations/tenant/01_identity_and_access',
                'database/migrations/tenant/02_assessment_and_exams',
                'database/migrations/tenant/03_ksa_and_competencies',
                'database/migrations/tenant/04_proctoring_and_security',
                'database/migrations/tenant/05_rules_and_penalties',
                'database/migrations/tenant/06_hybrid_governance_and_cohorts',
                'database/migrations/tenant/07_integration_and_system_ops',
                'database/migrations/landlord',                   
            ],
        ]);

        // 3. run the main Seeder to populate data after all tables are built
        
        $this->seed(TenantMasterSeeder::class);
    }

    public function test_technical_evaluator_has_full_content_and_grading_capabilities(): void
    {
        $expected = [
            'questions.manage',
            'categories.manage',
            'competencies.manage',
            'exams.manage',
            'exams.publish',
            'grading.evaluate',
            'grading.view',
            'grading.publish',
            'workflows.manage',
        ];
        
        $this->assertRolePermissionsMatch(RoleName::TechnicalEvaluator->value, $expected);        
    }

    public function test_candidate_can_start_and_take_exam_sessions(): void
    {
        $expected = [
            'exams.view',
            'exam_sessions.start',
        ];

        $this->assertRolePermissionsMatch(RoleName::Candidate->value, $expected);
    }

    private function assertRolePermissionsMatch(string $roleName, array $expected): void
    {
        $actual = DB::table('role_permissions')
            ->join('roles', 'roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'permissions.permission_id', '=', 'role_permissions.permission_id')
            ->where('roles.role_name', $roleName)
            ->pluck('permissions.permission_name')
            ->sort()
            ->values()
            ->all();

        sort($expected);

        $this->assertEquals(
            $expected,
            $actual,
            " permissions for role '{$roleName}' have changed from expected. "
            . " if this change is intentional, please update the list in this test as well. "
        );
    }
}