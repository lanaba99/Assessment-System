# Assessment System — Project Status Audit

**Audit date:** 2026-06-15  
**Scope:** Full scan of `app/Domains/` (14 domains), route files, test suite, tenancy isolation  
**Status:** Feature-complete at the domain layer; integration tests blocked by environment + permission/tenancy wiring gaps

---

## Executive Summary

The codebase is a well-structured DDD multi-tenant Laravel application with **14 domains** under `app/Domains/`. HTTP controllers live in `app/Http/Controllers/{Domain}/` (not inside domain folders). Business logic, policies, listeners, and repositories are domain-local.

| Layer | Status |
|-------|--------|
| **Central (landlord) API** | Partial — login + tenant CRUD only |
| **Tenant API** | Broad — identity, content, exams, sessions, grading, penalties, workflows, analytics |
| **Internal-only domains** | Rules (eligibility engine), Shared (traits) |
| **Test suite** | ~170+ feature tests across 18 files; **currently failing at bootstrap** due to missing `pdo_sqlite` in WSL PHP |
| **Tenancy** | Production: per-DB via stancl/tenancy + subdomain; Tests: mocked `tenant()` + middleware bypass |

### Critical Findings (read before fixing individual modules)

1. **Test environment blocker:** `phpunit.xml` sets `DB_CONNECTION=sqlite` but WSL PHP lacks `pdo_sqlite`. `UsesCentralSchema` and `UsesGradingSchema` have **no MySQL fallback** (unlike `UsesIdentitySchema`). All schema-bootstrapped tests fail immediately with `could not find driver`.
2. **Permission naming drift:** Policies check granular names (`exams.view`, `grading.publish`, `exam_sessions.start`, …) but `TenantMasterSeeder` seeds **legacy names** (`evaluations.score`, `sessions.proctor`, `exams.take`). `IdentityPermissionsSeeder` is closer but **incomplete** (missing `categories.manage`, `questions.manage`, `competencies.manage`, `exams.view`, `exams.manage`). Seeded tenant roles will get 403s in production.
3. **Missing core route:** `PATCH /api/v1/users/{userId}` — `UserPolicy@update` exists, `users.update` permission exists, **no controller method or route**.
4. **Central Sanctum guard:** Central routes use `auth:sanctum` without `auth:central`; works via bearer token lookup today but `config/sanctum.php` only lists `web` guard — fragile for SPA/session auth.
5. **Proctoring authorization gap:** `proctoring.ingest` / `proctoring.view` permissions are seeded but **never enforced** in `ProctorEventController` or `LogProctorEventRequest`.
6. **Rules domain:** Full eligibility engine implemented; **zero HTTP routes** for rule/chain CRUD (Tenant Admin cannot configure eligibility via API).

---

## Domain Inventory

### Directory Map (`app/Domains/`)

```
Analytics/     Central/       Cohorts/       Competency/
ExamEngine/    ExamSession/   Grading/       Identity/
Penalties/     Proctoring/    QuestionBank/  Rules/
Shared/        Workflows/
```

**Total domain PHP files:** ~323  
**Service providers (auto-registered via `DomainServiceProvider`):** 13 (all except Shared)

### Architecture Pattern

```
HTTP Request
    │
    ├─ Central host (localhost) ──► routes/api.php ──► Central/* Controllers
    │                                      │
    │                                      └─► Central domain services (landlord DB)
    │
    └─ Tenant subdomain (*.localhost) ──► routes/tenant.php ──► Domain Controllers
                                                   │
                                                   ├─ InitializeTenancyBySubdomain
                                                   ├─ PreventAccessFromCentralDomains
                                                   └─► Domain services (tenant DB)
```

---

## Domain Status Table

| Domain | Current Status | Implemented (Controllers / Services / Jobs / Listeners) | Routes (Central / Tenant) | Missing Controllers / Routes | Priority |
|--------|----------------|----------------------------------------------------------|---------------------------|------------------------------|----------|
| **Central** | Partial | **Services:** `CentralAuthService`, `TenantManagementService`. **Models:** 17 landlord models (AuditLog, WebhookConfig, BackupSchedule, …). **Policy:** `CentralTenantPolicy`. **No Jobs/Listeners.** | **Central:** `GET /api/ping`, `POST /api/v1/admin/auth/login`, `GET/POST/PATCH /api/v1/admin/tenants*` | No central admin CRUD, tenant suspend/delete, domain management, audit log API, webhook/backup APIs. Models exist without services. | **P0** |
| **Identity** | Implemented | **Services:** Auth, UserManagement, RoleManagement, MFA, Authorization, SecurityPolicy (6). **Policies:** User, Role, SecurityPolicy. **14 models**, 12 repositories. | **Tenant:** `auth/*`, `users/*`, `roles/*`, `security/*`, `identity/*`, `system/status` | **`PATCH users/{userId}`** (policy exists, route missing). No `users/{id}/activate`. MFA enrollment routes absent. Department management (model exists, no API). | **P1** |
| **QuestionBank** | Implemented | **Services:** QuestionManagement, CategoryTree, QuestionBank (item resolution), PsychometricAnalysis. **Job:** `CalculateQuestionMetricsJob`. **Listener:** `RecalculatePsychometricsListener`. **Strategies:** 8 selection + 6 question types. | **Tenant:** `categories/*` (tree, store, move, destroy), `questions/*` (full CRUD) | No category/question show-by-id GET. No bulk import API (migration exists). Psychometrics read API missing. | **P2** |
| **Competency** | Implemented | **Service:** `CompetencyTreeServiceImpl`. **Policy:** `CompetencyPolicy`. | **Tenant:** `competencies/*` (tree, store, move, destroy) | No single-node GET. No question-competency weight API (table exists in Grading schema). | **P2** |
| **ExamEngine** | Implemented | **Services:** ExamEngine, QuestionSelection, BlueprintAssembler. **Policy:** `ExamPolicy`. | **Tenant:** `exams/*` (CRUD + publish/archive) | No blueprint section management routes (managed via exam PATCH). No exam clone/duplicate. | **P2** |
| **ExamSession** | Implemented | **Services:** ExamSession, Enrollment. **States:** 7-state machine. **Events:** ResponseSubmitted, ExamSessionCompleted. **Policy:** `ExamSessionPolicy`. | **Tenant:** `exam-sessions/*`, `exams/{examId}/enrollments/*` | No session list/index for admins. No enrollment bulk import. Legacy `ExamSessionController.php` at root (unwired). | **P1** |
| **Grading** | Implemented | **Services:** 13 (Grading, Finalization, ManualEval, ResultPublication, WeightedScoring, CompetencyScoring, PenaltyApplication, …). **Listeners:** 4 (ResponseSubmitted, SessionCompleted, ProcessFinalGrade, LogResultGenerated). **Policy:** `GradingPolicy`. | **Tenant:** pending-evaluations, score, result/publish, publication-status, `exam-sessions/{id}/result` | No grade read/list API for admins. No rubric CRUD (migrations exist). Certificate generation (migration exists, no service/route). | **P1** |
| **Proctoring** | Partial | **Service:** `ProctoringServiceImpl`. **Event:** proctor event logged. **No Policy class.** | **Tenant:** `exam-sessions/{id}/proctor-events` (store, index) | **`proctoring.ingest` / `proctoring.view` not enforced.** No proctor review/escalation API. Device fingerprint model/migration with no service. | **P1** |
| **Penalties** | Implemented | **Services:** RuleManagement, Evaluation, Sanction. **Listener:** evaluates proctor events. **Policy:** `PenaltyPolicy`. **Triggers:** 2 condition evaluators. | **Tenant:** `penalty-rules/*`, sanctions index/void | No sanction manual-create route. Rule templates (migration exists) have no API. | **P2** |
| **Rules** | Internal only | **Services:** `RuleEngineService`, `EligibilityEvaluatorService`. **Conditions:** PrerequisiteExam. **Models:** 5 (chains, conditions, actions, templates). | **None** — consumed by ExamSession enrollment/start | **Full CRUD missing** for eligibility chains, rule conditions, rule templates. Tenant Admin cannot configure rules via API. | **P1** |
| **Workflows** | Implemented | **Service:** `ApprovalWorkflowService`. **Policy:** `ApprovalWorkflowPolicy`. **Models:** 10 workflow state tables. | **Tenant:** `workflows/` (initiate, show, approve) | No reject/deny/cancel workflow. No workflow list/index. | **P2** |
| **Cohorts** | Implemented | **Services:** CohortManagement, CohortMember. **Policy:** `CohortPolicy`. | **Tenant:** `cohorts/*`, `cohorts/{id}/members/*` | No cohort analytics/dashboard (migration `group_dashboards` exists). | **P3** |
| **Analytics** | Partial | **Service:** `AnalyticsIngestionService`. **Listener:** `IngestResultGeneratedListener`. **Policy:** `AnalyticsPolicy`. **Models:** AnalyticsCache + 3 others. | **Tenant:** `GET analytics/dashboard` | No report execution API (migrations exist). No time-series/historical endpoints. Ingestion depends on `ResultGenerated` event chain. | **P1** |
| **Shared** | Complete | **Traits:** `UsesUuid`, `AutoFillsTenantId`, `BelongsToTenant` | N/A | N/A | **P3** |

---

## Route Coverage by Role

### Super Admin (Central)

| Capability | Route | Status |
|------------|-------|--------|
| Login | `POST /api/v1/admin/auth/login` | ✅ |
| List tenants | `GET /api/v1/admin/tenants` | ✅ |
| Create tenant | `POST /api/v1/admin/tenants` | ✅ |
| View tenant | `GET /api/v1/admin/tenants/{id}` | ✅ |
| Update tenant | `PATCH /api/v1/admin/tenants/{id}` | ✅ |
| Suspend/delete tenant | — | ❌ |
| Manage central admins | — | ❌ |
| View audit logs | — | ❌ (model exists) |
| Webhook/backup config | — | ❌ (models exist) |

### Tenant Admin

| Capability | Route | Status |
|------------|-------|--------|
| User CRUD | `POST/GET users`, invite, deactivate, reset-password | ⚠️ **Update missing** |
| Role assignment | `roles/*` full CRUD + assign/unassign | ✅ |
| Security policies | `GET/PATCH security/policies` | ✅ |
| Exam enrollment | `exams/{id}/enrollments/*` | ✅ |
| Cohort management | `cohorts/*` | ✅ |
| Penalty rules | `penalty-rules/*` | ✅ |
| Eligibility rules | — | ❌ |
| Analytics dashboard | `GET analytics/dashboard` | ✅ |

### Evaluator / Creator

| Capability | Route | Status |
|------------|-------|--------|
| Question bank | `categories/*`, `questions/*` | ✅ |
| Competency tree | `competencies/*` | ✅ |
| Exam templates | `exams/*` | ✅ |
| Manual grading | pending-evaluations, score | ✅ |
| Result publication | publish, publication-status | ✅ |
| Workflow approval | `workflows/*` | ✅ |
| Proctoring review | proctor-events index | ⚠️ No auth gate |

### Examinee (Candidate)

| Capability | Route | Status |
|------------|-------|--------|
| Login / MFA | `auth/*` | ✅ |
| Start session | `POST exam-sessions` | ✅ |
| Submit responses | `POST exam-sessions/{id}/responses` | ✅ |
| Suspend/resume/complete | session lifecycle routes | ✅ |
| View own result | `GET exam-sessions/{id}/result` | ✅ (publication-gated) |
| Proctoring events | `POST exam-sessions/{id}/proctor-events` | ✅ |
| Heartbeat | `POST exam-sessions/{id}/heartbeat` | ✅ |

---

## Tenancy Context Analysis

### Production Path

| Route file | Middleware | DB context | Host |
|------------|-----------|------------|------|
| `routes/api.php` | `api` (auto) | Central/landlord | `localhost`, `127.0.0.1` |
| `routes/tenant.php` | `api`, `InitializeTenancyBySubdomain`, `PreventAccessFromCentralDomains` | Tenant DB (`tenant_{uuid}`) | `{tenant}.localhost` |

Every tenant controller/service that mutates data calls `tenant()->getKey()` for scoping. Repositories add `where('tenant_id', $tenantId)` as defense-in-depth.

### Test Path

Feature tests use a **dual strategy**:

1. **`UsesIdentitySchema`** — checks `extension_loaded('pdo_sqlite')`; falls back to MySQL if missing.
2. **`UsesCentralSchema`, `UsesGradingSchema`, `UsesExamSessionSchema`, …** — always configure SQLite; **fail if driver missing**.
3. **`initializeTenantContext($tenantId)`** — mocks `Stancl\Tenancy\Contracts\Tenant` and binds to container; does **not** switch DB connections.
4. **`withoutTenancyIdentificationMiddleware()`** — disables subdomain middleware so `$this->getJson('/api/v1/...')` works without tenant host headers.
5. **`Sanctum::actingAs($user)`** — sets authenticated user; replaces need for Bearer header in most tests.

### Tenancy Risks Identified

| Risk | Location | Impact |
|------|----------|--------|
| `tenant()` null in tests without `initializeTenantContext` | Any controller calling `tenant()->getKey()` | 500 / null pointer |
| `AutoFillsTenantId` skips fill when `tenant()` is null | AnalyticsCache, other models | Rows created without `tenant_id` in direct service tests |
| Central tests don't disable tenant middleware | N/A for central routes | Central routes not affected |
| Permission checks bypass tenant DB isolation | AuthorizationService | Correct — uses same SQLite DB with tenant_id column |
| `auth:sanctum` without explicit guard on central routes | `routes/api.php` | Token auth works; session auth may resolve wrong provider |

---

## Test Suite Analysis

### Test Inventory

| File | Domain | Tests | Schema trait |
|------|--------|-------|--------------|
| `Central/CentralAdminTest.php` | Central | 3 | `UsesCentralSchema` |
| `Analytics/AnalyticsIngestionTest.php` | Analytics + Grading | 3 | `UsesAnalyticsSchema` |
| `Grading/ResultPublicationControllerTest.php` | Grading | 3 | `UsesGradingSchema` |
| `Grading/ResultPublicationServiceTest.php` | Grading | 6 | `UsesGradingSchema` |
| `Grading/GradeImmutabilityTest.php` | Grading | 4 | `UsesGradingSchema` |
| `Workflows/ApprovalWorkflowTest.php` | Workflows + Grading | 2 | `UsesGradingSchema` + `UsesWorkflowsSchema` |
| `Penalties/PenaltyResultIntegrationTest.php` | Penalties + Grading | 2 | `UsesPenaltiesSchema` |
| `ExamSession/ExamSessionLifecycleTest.php` | ExamSession | 35 | `UsesExamSessionSchema` |
| `ExamEngineModuleTest.php` | ExamEngine | 25 | `UsesExamEngineSchema` |
| `Cohorts/CohortLifecycleTest.php` | Cohorts | 30 | `UsesCohortSchema` |
| `QuestionBank/*` | QuestionBank | 15 | `UsesQuestionBankSchema` |
| `CompetencyModuleTest.php` | Competency | 7 | `UsesCompetencySchema` |
| `Identity/*` | Identity | 29 | `UsesIdentitySchema` |
| `Rules/EligibilityEvaluatorTest.php` | Rules | 4 | `UsesRulesSchema` |
| `Security/HardeningVerificationTest.php` | Cross-domain | 11 | Multiple |

### Confirmed Failure (Environment)

```
QueryException: could not find driver (Connection: sqlite, Database: :memory:)
  at tests/Feature/Central/UsesCentralSchema.php:37
```

**Root cause:** WSL PHP 8.x installed without `php-sqlite3` / `pdo_sqlite` extension.  
**Fix:** `sudo apt install php-sqlite3` (match installed PHP version) or align all schema traits with `UsesIdentitySchema`'s MySQL fallback.

### Expected Failures After SQLite Fix (Code-Level)

These are predicted from static analysis; re-run suite after environment fix to confirm.

| Test file | Likely failure mode | Root cause |
|-----------|--------------------|-----------|
| `CentralAdminTest` | 401 on tenant routes after login | Sanctum token for `CentralAdminUser` may not resolve if `personal_access_tokens.tokenable_type` morph map differs; verify landlord migration vs test schema |
| `CentralAdminTest` (create tenant) | Event faked but DB job pipeline | `Event::fake([TenantCreated])` prevents real tenant DB creation — test only checks event dispatch + landlord row |
| `AnalyticsIngestionTest` (ingest) | Cache row null or wrong tenant_id | Direct service call bypasses tenant mock unless `summary->tenantId` matches; should pass if `$this->tenantA` used consistently |
| `AnalyticsIngestionTest` (dashboard) | 403 Forbidden | `analytics.view` permission granted in test — should pass if Sanctum + policy wired |
| `AnalyticsIngestionTest` (listener) | `Event::assertListening` pass | Registration check only — should pass |
| `ResultPublicationControllerTest` | 403 or 404 | 403 if permission grant fails; 404 if `tenant()->getKey()` not mocked |
| `ResultPublicationControllerTest` (publish) | 422 `workflow_not_approved` | If workflow auto-initiation added later; currently should publish without workflow |
| `GradeImmutabilityTest` | Model exception tests | Service-layer — should pass with schema |
| `ApprovalWorkflowTest` | Multi-actor Sanctum switching | Pattern: `Sanctum::actingAs()` per actor — valid; depends on workflow tables migrated |
| `IdentityModuleTest` / others | 403 on seeded permissions | Tests use `grantPermissionsToUser()` inline — should pass |
| **Production seeded tenants** | Widespread 403 | Permission name mismatch between seeders and policies |

### Test Infrastructure Gaps

| Gap | Recommendation |
|-----|----------------|
| No shared `TenantTestCase` base | Extract `initializeTenantContext`, `withoutTenancyIdentificationMiddleware`, SQLite/MySQL bootstrap into one trait used by all domain schema traits |
| `RefreshDatabase` disabled in `Pest.php` | Each test file manually builds schema — consistent but duplicated |
| No central/tenant host simulation | Tests bypass subdomain middleware entirely — integration gap for host-based routing |
| No test verifying Bearer token flow end-to-end | Only `Sanctum::actingAs()` used — add at least one test with `withToken()` per domain |

---

## Cross-Domain Event Chain

```
ExamSessionCompleted
    └─► Grading: ExamSessionCompletedListener → AssessmentFinalizationService
            └─► ResultGenerated
                    ├─► Analytics: IngestResultGeneratedListener → AnalyticsCache
                    ├─► Grading: ProcessFinalGradeListener → FinalGradeProcessingService
                    └─► Grading: LogResultGeneratedListener (observability)

ResponseSubmitted
    └─► Grading: ResponseSubmittedListener → GradingService (auto-score)

ProctorEvent (Proctoring)
    └─► Penalties: PenaltyEvaluationListener → sanctions

Question metrics
    └─► QuestionBank: RecalculatePsychometricsListener
```

Any break in tenant context during event dispatch affects Analytics ingestion and penalty application.

---

## Step-by-Step Remediation Plan

Ordered by dependency — complete each phase before moving to the next.

### Phase 0 — Test Environment (Blocker) `P0`

- [ ] **0.1** Install `pdo_sqlite` in WSL: `sudo apt install php{version}-sqlite3 && sudo phpenmod sqlite3`
- [ ] **0.2** Verify: `php -m | grep pdo_sqlite`
- [ ] **0.3** Run full suite: `php artisan test` — capture baseline failure list
- [ ] **0.4** Unify schema traits: add MySQL fallback (copy pattern from `UsesIdentitySchema`) to `UsesCentralSchema`, `UsesGradingSchema`, and all domain schema traits
- [ ] **0.5** Create shared `Tests\Concerns\InteractsWithTenancy` trait consolidating `initializeTenantContext()` + `withoutTenancyIdentificationMiddleware()`

### Phase 1 — Central / Tenant Isolation `P0`

- [ ] **1.1** Central auth: add `'central'` to `config/sanctum.php` guards array; use `auth:sanctum,central` on central protected routes (or document bearer-only approach)
- [ ] **1.2** Verify `CentralAdminUser` morph type in `personal_access_tokens` matches Sanctum lookup (landlord migration vs test schema)
- [ ] **1.3** Add central route test with `withToken()` (not just `actingAs`) to validate real Bearer flow
- [ ] **1.4** Audit all controllers for bare `tenant()->getKey()` — ensure tests always call `initializeTenantContext()` in `beforeEach`
- [ ] **1.5** Add null-safe guard or test assertion when `tenant()` is unbound (fail fast with clear error)

### Phase 2 — Permission System Alignment `P0`

- [ ] **2.1** Create canonical `config/permissions.php` or expand `IdentityPermissionsSeeder` to include **all** policy-checked permissions:
  - `categories.manage`, `questions.manage`, `competencies.manage`
  - `exams.view`, `exams.manage`
  - `exam_sessions.start`, `exam_sessions.view`, `exam_sessions.manage`
  - `grading.evaluate`, `grading.view`, `grading.publish`
  - `proctoring.ingest`, `proctoring.view`
  - `penalties.view`, `penalties.manage`
  - `workflows.manage`, `workflows.approve`
  - `analytics.view`
  - `cohorts.view`, `cohorts.manage`, `cohorts.members.manage`
- [ ] **2.2** Update `TenantMasterSeeder` role matrix to use new permission names; remove legacy aliases (`evaluations.score`, `sessions.proctor`, `exams.take`)
- [ ] **2.3** Add migration/seeder to rename existing permission rows in deployed tenant DBs
- [ ] **2.4** Add test: seeded Tenant Admin role can access each major route group (smoke test per role)

### Phase 3 — Identity Gaps `P1`

- [ ] **3.1** Add `UserController@update` + `PATCH /api/v1/users/{userId}` route
- [ ] **3.2** Add `UpdateUserRequest` form request with `UserPolicy@update` authorization
- [ ] **3.3** Verify MFA enrollment/verify routes if required by spec
- [ ] **3.4** Re-run `IdentityModuleTest`, `CrossTenantIsolationTest`

### Phase 4 — Grading / Analytics / Workflows Integration `P1`

- [ ] **4.1** Run `Grading/*`, `Analytics/*`, `Workflows/*`, `Penalties/*` tests after Phase 0–2
- [ ] **4.2** Fix `ResultPublicationController` if 403/404 — trace `PublishResultRequest@authorize` → `GradingPolicy@publishResult`
- [ ] **4.3** Verify `AnalyticsIngestionService::ingest` writes correct `tenant_id` when called from listener (not just direct test call)
- [ ] **4.4** Confirm `ProcessFinalGradeListener` ordering relative to `IngestResultGeneratedListener` (both on `ResultGenerated`)
- [ ] **4.5** Validate workflow gate in `ResultPublicationServiceImpl` matches `ApprovalWorkflowTest` expectations

### Phase 5 — Authorization Hardening `P1`

- [ ] **5.1** Add `ProctoringPolicy` with `ingest` / `view` abilities; wire into `LogProctorEventController` and index action
- [ ] **5.2** Add `RulesPolicy` (when HTTP layer added)
- [ ] **5.3** Review `EnrollmentController` — confirm `manage-enrollments` gate maps to `ExamSessionPolicy@manageEnrollments`

### Phase 6 — Missing Domain APIs `P2`

- [ ] **6.1** Rules domain: add `EligibilityChainController` + routes for chain/condition CRUD
- [ ] **6.2** Central: tenant suspend/activate, delete, domain assignment routes
- [ ] **6.3** Analytics: report execution endpoints (migrations already exist)
- [ ] **6.4** Grading: certificate generation, rubric CRUD (migrations exist)
- [ ] **6.5** Workflows: reject/cancel + list endpoints
- [ ] **6.6** Remove dead `app/Http/Controllers/ExamSessionController.php` (unwired duplicate)

### Phase 7 — Final Validation `P2`

- [ ] **7.1** Full test suite green: `php artisan test`
- [ ] **7.2** Manual smoke: central login → create tenant → tenant login → create exam → enroll → session → grade → publish → analytics dashboard
- [ ] **7.3** Cross-tenant isolation manual test on subdomain hosts
- [ ] **7.4** Update this document with final pass/fail counts

---

## Quick Reference — All Tenant Routes (`routes/tenant.php`)

Prefix: `/api/v1` · Middleware: `api`, `InitializeTenancyBySubdomain`, `PreventAccessFromCentralDomains`  
Authenticated group: `auth:sanctum`

| Prefix | Controller | Domain |
|--------|-----------|--------|
| `auth/*` | Identity\AuthController | Identity |
| `system/status` | Identity\SystemController | Identity |
| `users/*` | Identity\UserController | Identity |
| `roles/*` | Identity\RoleController | Identity |
| `security/*` | Identity\SecurityController | Identity |
| `identity/*` | Identity\IdentityController | Identity |
| `categories/*` | QuestionBank\CategoryController | QuestionBank |
| `questions/*` | QuestionBank\QuestionController | QuestionBank |
| `competencies/*` | Competency\CompetencyController | Competency |
| `exams/*` | ExamEngine\ExamController | ExamEngine |
| `cohorts/*` | Cohorts\CohortController, CohortMemberController | Cohorts |
| `exam-sessions/*` | ExamSession\ExamSessionController, AssessmentResultController, ProctorEventController | ExamSession + Grading + Proctoring |
| `answer-evaluations/*` | Grading\ManualEvaluationController | Grading |
| `penalty-rules/*` | Penalties\PenaltyRuleController | Penalties |
| `sanctions/*` | Penalties\PenaltySanctionController | Penalties |
| `workflows/*` | Workflows\ApprovalWorkflowController | Workflows |
| `analytics/dashboard` | Analytics\AnalyticsDashboardController | Analytics |
| `exams/{id}/enrollments/*` | ExamSession\EnrollmentController | ExamSession |

---

## Quick Reference — Central Routes (`routes/api.php`)

Prefix: `/api` · No tenancy middleware

| Method | Path | Controller |
|--------|------|-----------|
| GET | `/api/ping` | Closure |
| POST | `/api/v1/admin/auth/login` | Central\AuthController |
| GET | `/api/v1/admin/tenants` | Central\TenantController |
| POST | `/api/v1/admin/tenants` | Central\TenantController |
| GET | `/api/v1/admin/tenants/{id}` | Central\TenantController |
| PATCH | `/api/v1/admin/tenants/{id}` | Central\TenantController |

Protected by: `auth:sanctum`, `central.admin`

---

*Generated by codebase audit. No fixes applied — remediation tracked in plan above.*
