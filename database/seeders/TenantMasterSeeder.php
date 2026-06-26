<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenantMasterSeeder extends Seeder
{
    private string $tenantId;
    private string $adminUserId;
    private array $roleIds = [];
    private array $permissionIds = [];
    private array $competencyIds = [];
    private string $categoryId;
    private array $questionIds = [];
    private array $versionIds = [];
    private string $examId;
    private string $cohortId;
    private array $candidateUserIds = [];

    public function run(): void
    {
        $this->tenantId = $this->resolveTenantId();

        DB::transaction(function () {
            $this->seedAdminUser();
            $this->seedRoles();
            $this->seedPermissions();
            $this->seedRolePermissions();
            $this->seedUserRoles();
            $this->seedSecurityPolicy();
            $this->seedCompetenciesAndLevels();
            $this->seedQuestionCategory();
            $this->seedQuestionsAndVersions();
            $this->seedQuestionCompetencyWeights();
            $this->seedExamAndBlueprint();
            $this->seedCohortAndMembers();
        });

        $this->command?->info("Tenant master seed complete. Tenant: {$this->tenantId}, Admin: {$this->adminUserId}, Cohort: {$this->cohortId}");
    }

    private function resolveTenantId(): string
    {
        if (function_exists('tenant') && tenant() !== null) {
            return (string) tenant()->getKey();
        }

        return (string) Str::uuid();
    }

    private function seedAdminUser(): void
    {
        
        $this->adminUserId = (string) Str::uuid();

        DB::table('users')->insert([
            'id' => $this->adminUserId,
            'tenant_id' => $this->tenantId,
            'external_employee_id' => 'EMP-000001',
            'email' => 'tenant.admin@alpha-engine.example',
            'password_hash' => Hash::make('password'),
            'first_name' => 'Tenant',
            'last_name' => 'Administrator',
            'user_type' => 'tenant_admin',
            'status' => 'active',
            'is_active' => true,
            'activated_at' => now(),
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedRoles(): void
    {
        $roles = [
            'Super Admin'         => ['category' => 'administrative', 'description' => 'Full system control across the tenant.'],
            'Proctor'             => ['category' => 'supervisory',    'description' => 'Live session monitoring and integrity enforcement.'],
            'Technical Evaluator' => ['category' => 'evaluation',     'description' => 'Manual scoring for open-ended/essay responses.'],
            'Candidate'           => ['category' => 'examinee',       'description' => 'Takes assigned assessments.'],
        ];

        $rows = [];
        foreach ($roles as $name => $meta) {
            $id = (string) Str::uuid();
            $this->roleIds[$name] = $id;

            $rows[] = [
                'role_id'        => $id,
                'tenant_id'      => $this->tenantId,
                'role_name'      => $name,
                'description'    => $meta['description'],
                'role_category'  => $meta['category'],
                'is_custom_role' => false,
                'is_system_role' => true,
                'role_metadata'  => json_encode(['seeded' => true]),
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        DB::table('roles')->insert($rows);
    }

    private function seedPermissions(): void
    {
        // Granular Identity-domain permissions checked by UserPolicy / RolePolicy /
        // SecurityPolicyPolicy. Must stay in lockstep with IdentityPermissionsSeeder.
        $identityPermissions = [
            ['name' => 'users.viewAny',              'resource' => 'users',             'action' => 'viewAny'],
            ['name' => 'users.view',                 'resource' => 'users',             'action' => 'view'],
            ['name' => 'users.create',               'resource' => 'users',             'action' => 'create'],
            ['name' => 'users.update',               'resource' => 'users',             'action' => 'update'],
            ['name' => 'users.deactivate',           'resource' => 'users',             'action' => 'deactivate'],
            ['name' => 'users.resetPassword',        'resource' => 'users',             'action' => 'resetPassword'],
            ['name' => 'roles.viewAny',              'resource' => 'roles',             'action' => 'viewAny'],
            ['name' => 'roles.view',                 'resource' => 'roles',             'action' => 'view'],
            ['name' => 'roles.create',               'resource' => 'roles',             'action' => 'create'],
            ['name' => 'roles.update',               'resource' => 'roles',             'action' => 'update'],
            ['name' => 'roles.delete',               'resource' => 'roles',             'action' => 'delete'],
            ['name' => 'roles.assign',               'resource' => 'roles',             'action' => 'assign'],
            ['name' => 'security_policies.view',     'resource' => 'security_policies', 'action' => 'view'],
            ['name' => 'security_policies.update',   'resource' => 'security_policies', 'action' => 'update'],
            ['name' => 'cohorts.view',               'resource' => 'cohort',            'action' => 'view'],
        ];

        // Coarser domain-action permissions kept for downstream domains.
        $domainPermissions = [
            ['name' => 'exams.manage',          'resource' => 'exam',     'action' => 'manage'],
            ['name' => 'exams.publish',         'resource' => 'exam',     'action' => 'publish'],
            ['name' => 'exams.view',            'resource' => 'exam',     'action' => 'view'], // تم التعديل
            ['name' => 'questions.manage',      'resource' => 'question', 'action' => 'manage'],
            ['name' => 'grading.evaluate',      'resource' => 'response', 'action' => 'evaluate'], // تم التعديل
            ['name' => 'exam_sessions.start',   'resource' => 'session',  'action' => 'start'],    // تم التعديل
            ['name' => 'users.manage',          'resource' => 'user',     'action' => 'manage'],
            ['name' => 'cohorts.manage',        'resource' => 'cohort',   'action' => 'manage'],
        ];

        $permissions = array_merge($identityPermissions, $domainPermissions);

        $rows = [];
        foreach ($permissions as $p) {
            $id = (string) Str::uuid();
            $this->permissionIds[$p['name']] = $id;

            $rows[] = [
                'permission_id'   => $id,
                'tenant_id'       => $this->tenantId,
                'permission_name' => $p['name'],
                'resource_type'   => $p['resource'],
                'action_type'     => $p['action'],
                'description'     => "Permission to {$p['action']} {$p['resource']}.",
                'created_at'      => now(),
            ];
        }

        DB::table('permissions')->insert($rows);
    }

    private function seedRolePermissions(): void
    {
        $matrix = [
            'Super Admin' => array_keys($this->permissionIds),
            'Proctor'     => ['exam_sessions.start'], // التسمية الجديدة
            'Technical Evaluator' => ['grading.evaluate', 'questions.manage'], // التسمية الجديدة
            'Candidate'   => ['exams.view'], // التسمية الجديدة
        ];

        $rows = [];
        foreach ($matrix as $roleName => $permissionNames) {
            foreach ($permissionNames as $permissionName) {
                $rows[] = [
                    'role_id'       => $this->roleIds[$roleName],
                    'permission_id' => $this->permissionIds[$permissionName],
                ];
            }
        }

        DB::table('role_permissions')->insert($rows);
    }

    private function seedUserRoles(): void
    {
        DB::table('user_roles')->insert([
            'user_id'     => $this->adminUserId,
            'role_id'     => $this->roleIds['Super Admin'],
            'assigned_at' => now(),
        ]);
    }

    /**
     * Tenants need an active SecurityPolicy row before SecurityController can
     * read or update it (otherwise GET returns 404, PATCH 404). Defaults are
     * deliberately permissive for dev seeding — MFA disabled, IP whitelisting
     * disabled. Tenants tighten these via PATCH /security/policies later.
     */
    private function seedSecurityPolicy(): void
    {
        DB::table('security_policies')->insert([
            'policy_id'                                 => (string) Str::uuid(),
            'tenant_id'                                 => $this->tenantId,
            'created_by_user_id'                        => $this->adminUserId,
            'mfa_enabled'                               => false,
            'mfa_method'                                => null,
            'password_min_length'                       => 12,
            'password_require_uppercase'                => true,
            'password_require_lowercase'                => true,
            'password_require_numbers'                  => true,
            'password_require_special_chars'            => true,
            'password_expiry_days'                      => null,
            'password_history_count'                    => null,
            'session_timeout_minutes'                   => 60,
            'session_absolute_timeout_hours'            => 12,
            'session_force_reauth_on_privilege_change'  => true,
            'ip_whitelisting_enabled'                   => false,
            'enable_biometric_auth'                     => false,
            'enforce_tls_1_3_minimum'                   => true,
            'disable_weak_ciphers'                      => true,
            'allowed_ip_ranges'                         => null,
            'updated_at'                                => now(),
        ]);
    }

    private function seedCompetenciesAndLevels(): void
    {
        $competencies = [
            ['name' => 'Analytical Reasoning',  'type' => 'cognitive',  'category' => 'core',      'code' => 'COMP-AR'],
            ['name' => 'Technical Proficiency', 'type' => 'technical',  'category' => 'core',      'code' => 'COMP-TP'],
            ['name' => 'Risk Management',       'type' => 'behavioral', 'category' => 'advanced',  'code' => 'COMP-RM'],
        ];

        $competencyRows = [];

        foreach ($competencies as $c) {
            $id = (string) Str::uuid();
            $this->competencyIds[$c['name']] = $id;

            $competencyRows[] = [
                'competency_id'           => $id,
                'tenant_id'               => $this->tenantId,
                'created_by_user_id'      => $this->adminUserId,
                'competency_name'         => $c['name'],
                'competency_code'         => $c['code'],
                'competency_type'         => $c['type'],
                'competency_category'     => $c['category'],
                'description'             => "Measures the candidate's {$c['name']}.",
                'competency_attributes'   => json_encode(['framework' => 'KSA']),
                'is_mandatory'            => true,
                'is_active'               => true,
                'proficiency_level_count' => 5,
                'created_at'              => now(),
                'updated_at'              => now(),
            ];
        }

        DB::table('competencies')->insert($competencyRows);
    }

    private function seedQuestionCategory(): void
    {
        $this->categoryId = (string) Str::uuid();

        DB::table('categories')->insert([
            'category_id'          => $this->categoryId,
            'tenant_id'            => $this->tenantId,
            'parent_category_id'   => null,
            'category_name'        => 'General Knowledge',
            'category_code'        => 'CAT-GEN',
            'category_description' => 'Root category for seeded sample questions.',
            'display_order'        => 1,
            'hierarchy_level'      => 0,
            'is_locked'            => false,
            'is_active'            => true,
            'category_metadata'    => json_encode(['seeded' => true]),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    private function seedQuestionsAndVersions(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $questionId = (string) Str::uuid();
            $versionId  = (string) Str::uuid();
            $this->questionIds[] = $questionId;
            $this->versionIds[]  = $versionId;

            DB::table('questions')->insert([
                'question_id'              => $questionId,
                'tenant_id'                => $this->tenantId,
                'category_id'              => $this->categoryId,
                'created_by_user_id'       => $this->adminUserId,
                'current_version_id'       => null,
                'question_title'           => "Sample Question #{$i}",
                'question_type'            => 'mcq',
                'difficulty_level'         => (($i - 1) % 5) + 1,
                'cognitive_level'          => (($i - 1) % 6) + 1,
                'is_randomizable'          => true,
                'requires_media_attachment'=> false,
                'is_deprecated'            => false,
                'is_archived'              => false,
                'total_usage_count'        => 0,
                'question_metadata'        => json_encode(['seeded' => true, 'index' => $i]),
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);

            DB::table('question_versions')->insert([
                'version_id'           => $versionId,
                'question_id'          => $questionId,
                'created_by_user_id'   => $this->adminUserId,
                'ver_num'              => 1,
                'question_text'        => "What is the correct answer to seeded question #{$i}?",
                'question_type'        => 'mcq',
                'question_stem'        => "Sample stem for question #{$i}.",
                // options live in the normalized question_options table (below);
                // the dead options_json column was dropped.
                'correct_answer_json'  => json_encode(['correct' => 'B']),
                'explanation_text'     => json_encode(['rationale' => 'Option B is correct by design of the seed.']),
                'evaluator_instructions' => json_encode([]),
                'approval_status'      => 'approved',
                'approved_by_user_id'  => $this->adminUserId,
                'usage_count_in_exams' => 0,
                'content_hash'         => hash('sha256', "q{$i}-v1"),
                'version_metadata'     => json_encode(['seeded' => true]),
                'created_at'           => now(),
                'approved_at'          => now(),
            ]);

            DB::table('questions')
                ->where('question_id', $questionId)
                ->update(['current_version_id' => $versionId]);

            $optionRows = [];
            $letters = ['A', 'B', 'C', 'D'];
            foreach ($letters as $idx => $letter) {
                $optionRows[] = [
                    'option_id'       => (string) Str::uuid(),
                    'version_id'      => $versionId,
                    'option_sequence' => $idx + 1,
                    'option_text'     => "Option {$letter}",
                    'is_correct'      => $letter === 'B',
                    'option_metadata' => json_encode([]),
                ];
            }
            DB::table('question_options')->insert($optionRows);
        }
    }

    private function seedQuestionCompetencyWeights(): void
    {
        $competencyKeys = array_keys($this->competencyIds);
        $rows = [];

        foreach ($this->questionIds as $idx => $questionId) {
            $primaryCompetencyName = $competencyKeys[$idx % count($competencyKeys)];

            $rows[] = [
                'weight_id'             => (string) Str::uuid(),
                'question_id'           => $questionId,
                'competency_id'         => $this->competencyIds[$primaryCompetencyName],
                'weight_percentage'     => 100,
                'skill_category'        => 'primary',
                'skill_gap_trigger'     => 'below_60',
                'is_primary_competency' => true,
                'weighting_metadata'    => json_encode(['seeded' => true]),
                'created_at'            => now(),
                'updated_at'            => now(),
            ];
        }

        DB::table('question_competency_weights')->insert($rows);
    }

    private function seedExamAndBlueprint(): void
    {
        $this->examId = (string) Str::uuid();

        DB::table('exams')->insert([
            'exam_id'                    => $this->examId,
            'tenant_id'                  => $this->tenantId,
            'created_by_user_id'         => $this->adminUserId,
            'exam_name'                  => 'Alpha Foundational Adaptive Exam',
            'exam_code'                  => 'EXAM-ALPHA-001',
            'exam_description'           => 'Seeded adaptive exam covering all three core competencies.',
            'exam_type'                  => 'certification',
            'assessment_mode'            => 'online',
            'total_questions'            => 10,
            'total_duration_minutes'     => 30,
            'pass_mark_percentage'       => 60,
            'difficulty_tier_level'      => 3,
            'is_adaptive_exam'           => true,
            'is_randomized'              => true,
            'allow_review_after_submit'  => true,
            'allow_flagging_for_review'  => true,
            'timer_visible_to_candidate' => true,
            'show_correct_answers_after' => false,
            'security_protocols'         => json_encode([
                'lockdown_browser' => true,
                'webcam_required'  => true,
            ]),
            'exam_metadata'              => json_encode(['seeded' => true]),
            'is_published'               => true,
            'exam_status'                => 'published',
            'published_at'               => now(),
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);

        $blueprintRows = [];
        foreach ($this->competencyIds as $name => $competencyId) {
            $blueprintRows[] = [
                'blueprint_id'           => (string) Str::uuid(),
                'exam_id'                => $this->examId,
                'competency_id'          => $competencyId,
                'min_questions_count'    => 2,
                'max_questions_count'    => 4,
                'min_weight_percentage'  => 20,
                'max_weight_percentage'  => 50,
                'bloom_distribution'     => json_encode([
                    'remember'   => 1,
                    'understand' => 1,
                    'apply'      => 1,
                ]),
                'blueprint_metadata'     => json_encode(['competency' => $name]),
                'created_at'             => now(),
            ];
        }
        DB::table('exam_blueprints')->insert($blueprintRows);
    }

    private function seedCohortAndMembers(): void
    {
        $this->cohortId = (string) Str::uuid();

        DB::table('cohorts')->insert([
            'cohort_id'          => $this->cohortId,
            'tenant_id'          => $this->tenantId,
            'parent_cohort_id'   => null,
            'created_by_user_id' => $this->adminUserId,
            'cohort_name'        => 'Q2 Engineering Batch',
            'cohort_code'        => 'COH-Q2-ENG',
            'cohort_type'        => 'training',
            'cohort_description' => 'Q2 engineering candidate cohort seeded for baseline development.',
            'hierarchy_level'    => 0,
            'cohort_attributes'  => json_encode(['department' => 'engineering', 'cycle' => 'Q2']),
            'is_active'          => true,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $userRows = [];
        $memberRows = [];
        $candidateRoleId = $this->roleIds['Candidate'];
        $userRoleRows = [];

        for ($i = 1; $i <= 5; $i++) {
            $userId = (string) Str::uuid();
            $this->candidateUserIds[] = $userId;

            $userRows[] = [
                'id'                   => $userId,
                'tenant_id'            => $this->tenantId,
                'external_employee_id' => 'EMP-' . str_pad((string) (100 + $i), 6, '0', STR_PAD_LEFT),
                'email'                => "candidate.{$i}@alpha-engine.example",
                'password_hash'        => Hash::make('password'),
                'first_name'           => "Candidate{$i}",
                'last_name'            => "Engineer",
                'user_type'            => 'examinee',
                'status'               => 'active',
                'is_active'            => true,
                'activated_at'         => now(),
                'email_verified_at'    => now(),
                'created_at'           => now(),
                'updated_at'           => now(),
            ];

            $memberRows[] = [
                'member_id'        => (string) Str::uuid(),
                'cohort_id'        => $this->cohortId,
                'user_id'          => $userId,
                'tenant_id'        => $this->tenantId,
                'membership_role'  => 'member',
                'added_at'         => now(),
                'is_active_member' => true,
            ];

            $userRoleRows[] = [
                'user_id'     => $userId,
                'role_id'     => $candidateRoleId,
                'assigned_at' => now(),
            ];
        }

        DB::table('users')->insert($userRows);
        DB::table('cohort_members')->insert($memberRows);
        DB::table('user_roles')->insert($userRoleRows);
    }
}
