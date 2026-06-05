ExamEngine Module — Completion Summary
1. What we achieved (Phases A → E)
Phase A — Stop the bleeding
Eliminated three critical runtime bugs in the pre-existing code before any new work began: ExamConfig model (phantom duplicate pointing at dropped schema), ExamFactory wrong FQCN, and double json_encode() on JSON columns.

Phase B — Gold-Standard skeleton
Aligned the module fully with the Competency gold standard across five ordered steps:

Enums & Contracts: ExamStatus enum encodes the full state machine (Draft → Published/Archived, Published → Archived, no reversal). ExamEngineService and QuestionSelectionService interfaces establish the public contracts.
Repository & Model: BelongsToTenant global scope + forceCreate/forceFill for server-controlled columns. tenant_id/created_by_user_id removed from $fillable.
Service implementation: ExamEngineServiceImpl delegates all transition logic to the enum; ExamNotFoundException replaces raw RuntimeException.
Policy & Provider: ExamPolicy enforces exams.view/exams.manage permissions with a same-tenant guard on every resource-level method. Both services bound in the provider.
HTTP layer: ExamController (7 endpoints), StoreExamRequest/UpdateExamRequest (typed toCommand() accessors), ExamResource/ExamSectionResource, wired inside the auth:sanctum group in routes/tenant.php.
Phase C — QuestionBank integration
Built the bridge between blueprint configuration and the QuestionBank's resolution engine:

At publish time: QuestionSelectionService::assertBlueprintFeasible validates each section's blueprint against QuestionBankService::analyzeCoverage. If any section has a coverage gap, publish is hard-blocked with a 422 blueprint_not_feasible response.
At session start: ExamSessionService::startSession now calls resolveQuestionsForSession, which uses BlueprintAssembler to translate per-competency blueprint rows into ItemResolutionRequest objects (one per section, respecting section_sequence order), calls QuestionBankService::resolveItems, and materialises exam_session_items rows within the session-creation transaction. Adaptive exams skip bulk pre-population.
Fixed a pre-existing bug: ExamSessionService was calling the renamed/resiganted findWithSectionsAndQuestions method.
Phase D — Database hygiene
Consolidated three fragmented migrations into a clean, ordered set:

Swapped 000230/000240 filenames so exam_sections sorts before exam_blueprints (FK dependency order).
exam_sections gained tenant_id + index + BelongsToTenant on the model.
exam_blueprints now reflects the final schema from the start — difficulty_distribution_* columns never existed in a fresh install.
The composite 000020 was split into two focused migrations: create_exam_session_items_table and add_heartbeat_to_exam_sessions.
Phase E — Test suite
25 Pest feature tests in ExamEngineModuleTest.php covering:

Schema drift guards (29 column-level assertions across 3 tables)
Full CRUD lifecycle through the HTTP stack
State machine: all valid transitions + all invalid transitions + no-partial-mutation regression guard
Policy: tenant isolation (404, not 403, for cross-tenant reads), unauthenticated 401
Blueprint feasibility: mock-based hard-block test confirming blueprint_not_feasible 422
2. Ready-to-use component map

app/Domains/ExamEngine/
│
├── Contracts/
│   ├── ExamEngineService.php          ← inject this; 7 lifecycle methods
│   └── QuestionSelectionService.php   ← inject this; feasibility + session resolution
│
├── DTOs/
│   ├── CreateExamCommand.php          ← input to createExam()
│   ├── UpdateExamCommand.php          ← input to updateExam() (PATCH semantics)
│   └── ResolvedSessionItem.php        ← cross-domain DTO; ExamEngine → ExamSession
│
├── Enums/
│   └── ExamStatus.php                 ← Draft/Published/Archived + canTransitionTo()
│
├── Exceptions/
│   ├── ExamNotFoundException.php      ← ::forId(string $examId)
│   ├── InvalidExamStateException.php  ← ::forOperation(string $op, string $state)
│   └── BlueprintNotFeasibleException.php ← ::forSections(string $examId, array $reports)
│
├── Models/
│   ├── Exam.php                       ← BelongsToTenant + UsesUuid + ExamStatus cast
│   ├── ExamSection.php                ← BelongsToTenant + UsesUuid
│   └── ExamBlueprint.php             ← UsesUuid; section_id FK to sections
│
├── Policies/
│   └── ExamPolicy.php                 ← viewAny/view/create/update/delete
│                                         AuthorizationService injection + sameTenant guard
├── Providers/
│   └── ExamEngineServiceProvider.php  ← binds both services + registers Gate::policy
│
├── Repositories/
│   └── ExamRepository.php             ← allForTenant, findById (tenant-scoped),
│                                         findWithSectionsAndBlueprintsForSession,
│                                         existsByCode, create/update/delete
└── Services/
    ├── ExamEngineServiceImpl.php      ← implements ExamEngineService
    ├── QuestionSelectionServiceImpl.php ← implements QuestionSelectionService
    └── BlueprintAssembler.php         ← internal; toSpec() and toRequest()
                                          (blueprint rows → QB DTOs)

app/Http/
├── Controllers/ExamEngine/ExamController.php   ← 7 routes, AuthorizesRequests
├── Requests/ExamEngine/
│   ├── StoreExamRequest.php                    ← toCommand(tenantId, userId)
│   └── UpdateExamRequest.php                   ← toCommand() + abort(404) guard
└── Resources/ExamEngine/
    ├── ExamResource.php                        ← @mixin Exam; exam_status->value
    └── ExamSectionResource.php                 ← @mixin ExamSection

database/
├── factories/
│   ├── ExamFactory.php                  ← published(), forUser(), forTenant()
│   ├── ExamSectionFactory.php           ← forExam(), withSequence()
│   └── ExamBlueprintFactory.php         ← forExam(), forSection(), forCompetency(),
│                                            stratified(), adaptive()
└── migrations/tenant/02_assessment_and_exams/
    ├── 2026_05_16_000220_create_exams_table.php
    ├── 2026_05_16_000230_create_exam_sections_table.php
    ├── 2026_05_16_000240_create_exam_blueprints_table.php
    ├── 2026_05_19_000020_create_exam_session_items_table.php
    └── 2026_05_19_000025_add_heartbeat_to_exam_sessions.php

tests/Feature/
├── ExamEngine/UsesExamEngineSchema.php   ← bootExamEngineSchema(), createExam()
└── ExamEngineModuleTest.php              ← 25 tests (64 assertions)
Routes (routes/tenant.php, inside auth:sanctum):


GET    /api/v1/exams              → ExamController@index
POST   /api/v1/exams              → ExamController@store
GET    /api/v1/exams/{examId}     → ExamController@show
PATCH  /api/v1/exams/{examId}     → ExamController@update
DELETE /api/v1/exams/{examId}     → ExamController@destroy
POST   /api/v1/exams/{examId}/publish  → ExamController@publish
POST   /api/v1/exams/{examId}/archive  → ExamController@archive
3. Clean-up commands
Run from your WSL terminal inside ~/assessment-system:


# 1. Clear all framework caches
./vendor/bin/sail php artisan optimize:clear

# 2. Run the full feature test suite
./vendor/bin/sail php vendor/bin/pest tests/Feature/ --no-coverage

# 3. (Optional) Run only the ExamEngine suite in isolation to confirm no regressions
./vendor/bin/sail php vendor/bin/pest tests/Feature/ExamEngineModuleTest.php --no-coverage
optimize:clear runs config:clear, route:clear, view:clear, cache:clear, and event:clear in one pass — everything that needs to be flushed before the next module's service provider is wired in.