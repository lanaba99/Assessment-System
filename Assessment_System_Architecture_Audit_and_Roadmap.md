# Online Assessment System — Architecture Audit & Feature Development Roadmap

**Project:** Multi-Tenant SaaS Assessment Platform  
**Current State:** Foundational multi-tenancy architecture complete, 14 autonomous domains established  
**Date:** May 2026  
**Architecture Lead:** Senior Backend Architect Review

---

## 1. ARCHITECTURE AUDIT — Where We Are

### ✅ Multi-Tenancy Setup: Confirmed Solid

Your implementation follows **per-database multi-tenancy** with:

- **Tenant Isolation**: Each tenant has isolated database (maximum security/compliance boundary)
- **AutoFillsTenantId Trait**: Automatic tenant context injection at model layer
  - Routes/middleware resolve tenant
  - Eloquent queries auto-scope to tenant_id
  - No risk of cross-tenant data leakage
- **Centralized Bootstrapping**: AppServiceProvider/TenantServiceProvider orchestrate initialization
- **Service Provider Pattern**: Each domain has its own provider (CompetencyServiceProvider, ExamEngineServiceProvider, etc.)
  - Domains are **lazy-loaded** (load only when needed)
  - Reduces bootstrap footprint
  - Testability improved by isolated DI containers

### ⚠️ Maturity Assessment by Layer

| Layer | Status | Confidence |
|-------|--------|-----------|
| **Models** | ~90% | Most domain models fully defined (Exam, ExamSession, Competency, Grade, etc.) |
| **Contracts/Interfaces** | ~85% | Service contracts exist; some need completion (AuthorizationService, TenantAccessService) |
| **Repositories** | ~40% | Interfaces defined; **implementations mostly incomplete** (Eloquent*Repository classes exist but lack queries) |
| **Services** | ~30% | Service interfaces + some stubs; **business logic largely not implemented** |
| **API Controllers** | ~10% | Minimal HTTP layer; API routes/controller structure needed |
| **Events & Listeners** | ~20% | Basic event classes exist; listener wiring incomplete |
| **Error Handling** | ~70% | Domain exceptions well-defined; centralized error handler partial |

---

## 2. THE 14 DOMAINS — Mapped & Prioritized

### Dependency Topology (Simplified)

```
┌─────────────────────────────────────────────────────────────────┐
│ FOUNDATION LAYER (No External Dependencies)                     │
├─────────────────────────────────────────────────────────────────┤
│ • Identity (Auth, Users, Roles, MFA)                            │
│ • Settings (Tenant & System Configuration)                      │
└─────────────────────────────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────────────────────────────┐
│ REFERENCE DATA LAYER (Low Coupling)                             │
├─────────────────────────────────────────────────────────────────┤
│ • Competency (Frameworks, Proficiency Levels)                   │
│ • Question (Item Banking, Content Repository)                   │
│ • Central (System-Wide: Audit, Webhooks, Feature Flags)         │
└─────────────────────────────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────────────────────────────┐
│ LEARNER & COHORT LAYER (Grouping)                               │
├─────────────────────────────────────────────────────────────────┤
│ • Cohorts (Learner Groups, Eligibility Checks)                  │
└─────────────────────────────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────────────────────────────┐
│ EXAM ENGINE LAYER (Design-Time)                                 │
├─────────────────────────────────────────────────────────────────┤
│ • ExamEngine (Blueprints, Sections, Configuration)              │
└─────────────────────────────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────────────────────────────┐
│ EXECUTION LAYER (Runtime - Core Business)                       │
├─────────────────────────────────────────────────────────────────┤
│ • ExamSession (State Machine: Pending → Active → Completed)     │
│ • Grading (Evaluation, Rubrics, Results)                        │
└─────────────────────────────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────────────────────────────┐
│ OPERATIONAL LAYER (Governance & Reporting)                      │
├─────────────────────────────────────────────────────────────────┤
│ • Workflows (Approval Chains, Governance)                       │
│ • Governance (Compliance, Rules, Penalties)                     │
│ • Analytics (Reporting, Caching, Dashboards)                    │
│ • Notifications (Events, Email, Webhooks)                       │
│ • Integration (External Systems, SSO, LMS Sync)                 │
└─────────────────────────────────────────────────────────────────┘
```

---

### Domain Details: Build Order & Blockers

#### **1. Identity** — Foundation [NEXT TARGET]
**Status**: 85% complete (models + contracts ready)
**Current Models**: User, Role, Permission, Department, IpWhitelist, LoginAttempt, MfaDevice, UserSession, UserPreference

**What's Done**:
- ✅ Policies (UserPolicy, RolePolicy, SecurityPolicyPolicy)
- ✅ Contracts (AuthenticationService, AuthorizationService, UserManagementService, RoleManagementService, MfaService, SecurityPolicyService)
- ✅ Exceptions (AuthenticationFailedException, InsufficientPermissionsException, MfaVerificationFailedException, etc.)
- ✅ DTOs (AuthenticationResult, PasswordValidationResult, MfaEnrollmentResult)

**What Needs Work**:
- 🔴 **AuthenticationServiceImpl** — Login flow, MFA enrollment/verification, password validation
- 🔴 **AuthorizationServiceImpl** — Permission checking, role-based access decisions
- 🔴 **UserManagementServiceImpl** — User CRUD, activation/deactivation, password reset
- 🔴 **RoleManagementServiceImpl** — Role assignment, permission grant/revoke
- 🔴 **MfaServiceImpl** — TOTP generation, device registration, verification flow

**Blocker for MVP**: No, but critical for ALL other domains. **Build first.**

---

#### **2. Competency** — Reference Data [SECOND]
**Status**: 75% complete (models + contracts ready)
**Current Models**: Competency, CompetencyFramework, CompetencyLevel, ProficiencyLevel, Skill, Ability, KnowledgeItem

**What's Done**:
- ✅ Contracts (CompetencyService, CompetencyFrameworkService, ProficiencyLevelService)
- ✅ Exceptions (CompetencyNotFoundException, DuplicateCompetencyException, InvalidCompetencyHierarchyException, etc.)
- ✅ DTOs (CreateCompetencyData, UpdateCompetencyFrameworkData, etc.)

**What Needs Work**:
- 🔴 **EloquentCompetencyRepository** — Query frameworks, list competencies, validate hierarchy
- 🔴 **CompetencyServiceImpl** — Hierarchy validation, framework cloning, competency mapping
- 🔴 **ProficiencyLevelServiceImpl** — CRUD for proficiency levels, scale validation
- 🔴 **Framework versioning** — Support multiple framework versions (design choice needed)

**Blocker for MVP**: Moderate. Questions link to competencies; grading measures competency gain. **Build 2nd.**

---

#### **3. Question** — Content Repository [THIRD]
**Status**: 65% complete (models defined in file structure)
**Current Models**: Question, QuestionTag, QuestionPool, QuestionType, AnswerOption

**What's Done**:
- ✅ Models (Question, AnswerOption, QuestionType, QuestionTag)
- ✅ Basic structure for item banking

**What Needs Work**:
- 🔴 **Contracts** — QuestionRepository, QuestionService, QuestionPoolService interfaces
- 🔴 **Services** — Filtering by competency, difficulty, type; versioning questions
- 🔴 **Repositories** — Query by pool, tag, competency; flagging and retirement
- 🔴 **Analytics** — Item difficulty, discrimination index, review history
- 🔴 **Import/Export** — QTI 2.1 or CSV import for bulk question loading

**Blocker for MVP**: High. ExamBlueprints pull from question pools. **Build 3rd.**

---

#### **4. ExamEngine** — Blueprint & Design [FOURTH]
**Status**: 70% complete (models + basic structure)
**Current Models**: Exam, ExamBlueprint, ExamSection, ExamConfig

**What's Done**:
- ✅ Models (Exam, ExamBlueprint, ExamSection, ExamConfig)
- ✅ Service contract (ExamEngineService)
- ✅ DTOs (CreateExamCommand, ExamView)
- ✅ Exception (InvalidExamStateException)

**What Needs Work**:
- 🔴 **ExamEngineServiceImpl** — Blueprint creation, section allocation, question assignment
- 🔴 **Blueprint Validation** — Check question counts, difficulty balance, competency coverage
- 🔴 **Section Management** — Sequential vs. non-sequential, timed sections, adaptive logic
- 🔴 **Exam States** — Draft → Published → Active → Closed (state machine)
- 🔴 **Configuration Versioning** — Support exam config changes without affecting live sessions

**Blocker for MVP**: HIGH. Core exam definition system. **Build 4th.**

---

#### **5. Cohorts** — Learner Groups [FIFTH]
**Status**: 40% complete (models only)
**Current Models**: Cohort, CohortMember

**What's Done**:
- ✅ Models (Cohort, CohortMember)

**What Needs Work**:
- 🔴 **Contracts & Services** — CohortService, CohortMemberService, EligibilityService
- 🔴 **Eligibility Rules** — Prerequisites, enrollment windows, max attempts, score thresholds
- 🔴 **Bulk Operations** — Add/remove members, batch enrollment from CSV
- 🔴 **Integration with ExamSession** — Eligibility checking before session creation
- 🔴 **Reporting** — Cohort performance, progress tracking

**Blocker for MVP**: Moderate. Optional for first MVP (single-candidate testing), essential for scaling.

---

#### **6. ExamSession** — Exam Execution [CRITICAL PATH]
**Status**: 80% complete (state machine + models ready)
**Current Models**: ExamSessionItem, QuestionResponse, CandidateExamStatus, ExamCandidateEligible

**What's Done**:
- ✅ State machine (ExamSessionState, ActiveState, CompletedState, PendingState, SuspendedState, TerminatedState)
- ✅ Models (ExamSessionItem, QuestionResponse, CandidateExamStatus)
- ✅ Contracts & basic services
- ✅ Events (ExamSessionCompleted, ResponseSubmitted)
- ✅ Repositories (SessionRepository, ExamSessionItemRepository)

**What Needs Work**:
- 🔴 **SessionRepositoryImpl** — Query by candidate, session state, time elapsed
- 🔴 **ExamSessionServiceImpl** — Initialize session, validate responses, state transitions
- 🔴 **Response Handling** — Submission validation, time limit enforcement, version locking (StaleVersionLockException)
- 🔴 **Session Recovery** — Resume interrupted sessions, crash handling
- 🔴 **Proctoring Integration** — Flags for suspicious activity (optional for MVP)

**Blocker for MVP**: CRITICAL. This IS the exam-taking experience.

---

#### **7. Grading** — Evaluation & Scoring [CRITICAL PATH]
**Status**: 80% complete (strategies + models ready)
**Current Models**: AssessmentResult, Grade, Rubric, RubricCriterion, AnswerEvaluation, CompetencyScore

**What's Done**:
- ✅ Multiple grading strategies (MultipleChoiceStrategy, ManualReviewStrategy, etc.)
- ✅ Event listeners (ExamSessionCompletedListener, ResponseSubmittedListener)
- ✅ Rubrics & criteria models
- ✅ Contracts & service interfaces
- ✅ DTOs (GradingRequest, GradingResult, AssessmentResultView)

**What Needs Work**:
- 🔴 **GradingServiceImpl** — Orchestrate strategy selection, invoke appropriate strategy
- 🔴 **MultipleChoiceStrategy** — Auto-grade MC responses, handle partial credit
- 🔴 **ManualReviewStrategy** — Queue for human review, mark as pending
- 🔴 **AssessmentResultServiceImpl** — Finalize results, calculate competency gain
- 🔴 **AssessmentFinalizationServiceImpl** — Issue certificates, trigger notifications, webhook calls
- 🔴 **Rubric Scoring** — Apply rubric criteria, aggregate scores

**Blocker for MVP**: CRITICAL. Exam results depend on this.

---

#### **8. Central** — System Infrastructure [PARALLEL]
**Status**: 50% complete (models defined)
**Current Models**: AuditLog, ApiAuditLog, TenantSetting, FeatureFlag, EmailTemplate, WebhookConfig, WebhookDeliveryLog, BackupLog, BackupSchedule, DataMigrationLog, NotificationSetting, SystemHealthLog, SystemSyncLog, ExternalIdentityMapping, TenantApiKey, WhiteLabelConfig

**What's Done**:
- ✅ Models for all system-wide concerns
- ✅ Audit trail structure

**What Needs Work**:
- 🔴 **AuditService** — Log user actions (create, update, delete), store in AuditLog
- 🔴 **FeatureFlagService** — Toggle features per tenant, cache flags
- 🔴 **EmailTemplateService** — Store, render, send email templates
- 🔴 **WebhookService** — Register webhooks, queue deliveries, retry logic
- 🔴 **BackupService** — Automated backup scheduling, encryption, retention
- 🔴 **SystemHealthMonitor** — CPU, disk, DB health checks

**Blocker for MVP**: Low. Nice-to-have but not blocking exam functionality. **Do in parallel.**

---

#### **9. Notifications** — Event-Driven Communication [FOUNDATION]
**Status**: 0% (implied, not shown in detail)

**What Needs Building**:
- 🔴 **NotificationService** — Send notifications via email, SMS, push, webhooks
- 🔴 **Event Listeners** — Listen to ExamSessionCompleted, ResultGenerated, RoleAssigned, etc.
- 🔴 **Queue Integration** — Use Laravel Queue for async delivery
- 🔴 **Template System** — Dynamic email/SMS templates with variables
- 🔴 **Delivery Status** — Track sent/delivered/failed

**Blocker for MVP**: Low-medium. Exam takes top priority; notifications can be basic (email only) in MVP.

---

#### **10. Workflows** — Approval & Governance [POST-MVP]
**Status**: 5% (models structure implied)

**What Needs Building**:
- 🔴 **Workflow Engine** — State machines for multi-step approvals
- 🔴 **Approval Models** — ApprovalWorkflow, ApprovalStep, ApprovalTask
- 🔴 **Rule Engine** — Define who approves what, based on rules
- 🔴 **Notification Integration** — Notify approvers of pending tasks

**Blocker for MVP**: None. Optional for MVP. Critical for enterprise (exam publication approval, etc.).

---

#### **11. Governance** — Rules & Penalties [POST-MVP]
**Status**: 5% (structure implied)

**What Needs Building**:
- 🔴 **Rule Engine** — Define exam policies (re-attempt rules, score thresholds, timing constraints)
- 🔴 **Penalty Models** — Penalties for cheating, late submission, etc.
- 🔴 **Eligibility Rules** — Pre-requisite exams, waiting periods, max attempts
- 🔴 **Compliance Checks** — GDPR data retention, accessibility compliance

**Blocker for MVP**: None. Optional for MVP. Critical for regulated industries.

---

#### **12. Analytics** — Reporting & Dashboards [POST-MVP]
**Status**: 40% (models defined)
**Current Models**: AnalyticsCache, GeneratedReport, ReportTemplate, ScheduledReport

**What Needs Building**:
- 🔴 **Report Service** — Query-builder for custom reports (questions, response patterns, result distributions)
- 🔴 **Caching** — Pre-compute aggregates (exam mean score, pass rate, difficulty discrimination index)
- 🔴 **Dashboard Data** — Real-time stats for admin console
- 🔴 **Export** — PDF, Excel, CSV reports
- 🔴 **Scheduled Reports** — Email reports on schedule (daily, weekly, monthly)

**Blocker for MVP**: None. Post-MVP feature.

---

#### **13. Integration** — External Systems [POST-MVP]
**Status**: 5% (ExternalIdentityMapping model only)

**What Needs Building**:
- 🔴 **SSO Integration** — OIDC, SAML, OAuth2
- 🔴 **LMS Connectors** — Canvas, Blackboard, Moodle (LTI 1.3)
- 🔴 **REST API Webhooks** — Send exam results to external systems
- 🔴 **CSV Bulk Operations** — Import questions, users, cohorts from external sources

**Blocker for MVP**: None. Post-MVP feature. Can use webhooks for basic integration.

---

#### **14. Settings** — System & Tenant Configuration [FOUNDATION]
**Status**: 30% (TenantSetting model in Central)

**What Needs Building**:
- 🔴 **SettingsService** — Get/set system and tenant configs
- 🔴 **Cache** — Cache settings with invalidation
- 🔴 **UI/API** — Settings management endpoints
- 🔴 **Validation** — Validate setting values (e.g., min/max session duration)
- 🔴 **Feature Flags** — Control features per tenant

**Blocker for MVP**: Low. Settings can be hardcoded initially; add flexibility later.

---

## 3. THE GOLDEN PATH TO MVP

### Phase 1: Exam Delivery Pipeline (Weeks 1–5)
**Goal**: Create an exam, take it, get graded. End-to-end flow.

#### Week 1: Foundation
**Identity** domain completion
- [ ] AuthenticationServiceImpl — Basic login/logout
- [ ] AuthorizationServiceImpl — Permission checks
- [ ] UserManagementServiceImpl — User CRUD
- [ ] API routes for auth (login, logout, profile)

**Deliverable**: Candidates can log in, system knows their identity.

#### Week 2: Reference Data Setup
**Competency** + **Question** domains
- [ ] CompetencyServiceImpl — Framework CRUD, basic hierarchy
- [ ] Question module — Question CRUD, pools, tagging
- [ ] API routes (create competency, create question)

**Deliverable**: You can build a competency framework and load questions.

#### Week 3: Exam Design
**ExamEngine** domain
- [ ] ExamEngineServiceImpl — Create exam from blueprint
- [ ] Blueprint validation — Check question counts
- [ ] Exam state machine — Draft → Published
- [ ] API routes (create exam, publish exam, list exams)

**Deliverable**: You can design an exam and publish it.

#### Week 4: Exam Execution
**ExamSession** domain
- [ ] SessionRepositoryImpl — Query sessions
- [ ] ExamSessionServiceImpl — Start session, submit responses
- [ ] State transitions — Pending → Active → Completed
- [ ] Response validation — Time limits, version locking
- [ ] API routes (start session, submit response, finish session)

**Deliverable**: Candidates can take the exam in real-time.

#### Week 5: Grading & Results
**Grading** domain
- [ ] GradingServiceImpl — Route to correct strategy
- [ ] MultipleChoiceStrategy — Auto-grade MC
- [ ] AssessmentResultServiceImpl — Finalize results
- [ ] CompetencyScore calculation
- [ ] API routes (get results, view assessment)

**Deliverable**: Exam is scored; candidate sees results. MVP complete.

---

### Phase 2: Learner Management (Weeks 6–7)
**Cohorts** domain
- Eligibility checking before session creation
- Bulk enrollment
- (Optional in MVP: restriction to cohort membership only)

---

### Phase 3: Operations & Reporting (Weeks 8–10)
- **Central** — Audit logs
- **Analytics** — Basic dashboard, pass rate, mean score
- **Notifications** — Email on exam completion, result notification

---

## 4. DEPENDENCY MANAGEMENT — Avoiding Circular References

### Rule 1: Event-Driven Architecture
**Don't hard-couple domains. Use Laravel Events.**

```php
// ExamSession fires event when session completes
// ExamSessionCompleted extends Event
event(new ExamSessionCompleted($session));

// Grading listens (no hard dependency)
class ExamSessionCompletedListener {
    public function handle(ExamSessionCompleted $event) {
        GradingService::grade($event->session);
    }
}
```

**Benefits**:
- ExamSession doesn't import Grading
- Grading doesn't import ExamSession
- Easy to add new listeners (Workflows, Analytics) without coupling

---

### Rule 2: Contracts Over Implementations
**All inter-domain communication uses interfaces.**

```php
// Bad: Importing concrete class
use App\Domains\Grading\Services\GradingService;
public function __construct(GradingService $service) {}

// Good: Importing contract/interface
use App\Domains\Grading\Contracts\GradingService;
public function __construct(GradingService $service) {}
```

**In ServiceProvider**:
```php
$this->app->bind(
    GradingService::class,
    GradingServiceImpl::class
);
```

**Benefits**:
- Implementation can change without affecting consumers
- Mock in tests easily
- Circular imports impossible (interfaces are abstract)

---

### Rule 3: One-Way Dependencies
**Define a clear dependency direction. No bi-directional imports.**

```
Identity → Competency → Question → ExamEngine → ExamSession → Grading
           ↓
         Central
```

**If A imports B, B never imports A.**

**Enforcement**:
```php
// In Competency ServiceProvider, this is OK:
use App\Domains\Identity\Contracts\UserManagementService;

// In Identity ServiceProvider, this is NOT OK:
use App\Domains\Competency\Services\CompetencyService; // ❌ Circular risk
```

---

### Rule 4: DTOs for Cross-Domain Data Transfer
**Never pass Eloquent models between domains. Use DTOs.**

```php
// Bad: ExamSession passes QuestionResponse (model) to Grading
Grading::grade($questionResponse);

// Good: ExamSession passes DTO to Grading
$gradingRequest = new GradingRequest(
    competencyId: $response->question->competency_id,
    selectedAnswerId: $response->answer_id,
    correctAnswerId: $response->question->correct_answer_id
);
Grading::grade($gradingRequest);
```

**Benefits**:
- Models stay private within their domain
- API contract is explicit
- Easy to validate input
- Reduces tight coupling

---

### Rule 5: Repositories as Domain Boundaries
**Other domains access data ONLY through service methods, never Eloquent directly.**

```php
// Bad: Accessing Grading models from ExamSession
$results = AssessmentResult::where('exam_session_id', $sessionId)->get();

// Good: Use GradingService
$results = $this->gradingService->getResultsBySession($sessionId);
```

**Benefits**:
- Grading can change its storage (e.g., ES, NoSQL) without impacting others
- Query optimization happens in one place
- Audit/security logic centralized

---

### Rule 6: Explicit Domain Contracts
**Document what each domain exposes.**

```php
namespace App\Domains\ExamSession\Contracts;

interface ExamSessionService {
    public function createSession(CreateSessionCommand $cmd): ExamSessionView;
    public function submitResponse(SubmitResponseCommand $cmd): void;
    public function completeSession(string $sessionId): void;
}
```

**Only expose these methods. Everything else is internal.**

---

## 5. IMPLEMENTATION CHECKLIST — Next 2 Weeks

### Week 1 Priority
- [ ] **Identity**
  - [ ] AuthenticationServiceImpl
  - [ ] AuthorizationServiceImpl
  - [ ] API controller: AuthController (login, logout, profile)
  - [ ] Middleware: Auth, Tenant resolution
  - [ ] Tests: Authentication flow, permission checking

- [ ] **Settings** (lightweight)
  - [ ] SettingsService — Simple cache wrapper
  - [ ] Read/write tenant config

### Week 2 Priority
- [ ] **Competency**
  - [ ] EloquentCompetencyRepository — All queries
  - [ ] CompetencyServiceImpl
  - [ ] API controller: CompetencyController
  - [ ] Tests

- [ ] **Question**
  - [ ] Question module structure
  - [ ] QuestionService, QuestionRepository
  - [ ] API controller
  - [ ] Tests

---

## 6. CRITICAL BLOCKERS & UNKNOWNS

### 1. **Rules/Eligibility Engine**
- **Blocker**: Cohort eligibility rules (prerequisites, max attempts, waiting periods) not yet specified.
- **Decision needed**: How do you define eligibility? Configuration file? UI? Both?
- **Impact**: Blocks cohort enrollment and exam session validation.

### 2. **Penalties Domain**
- **Blocker**: How penalties work? Applied during grading? Deducted from score? Disqualify candidate?
- **Decision needed**: Penalty model and calculation strategy.
- **Impact**: Blocks governance workflow.

### 3. **Workflows/Approval**
- **Blocker**: Do you need approval workflows for MVP, or hardcode to auto-publish exams?
- **Decision needed**: Is exam approval required?
- **Recommendation**: Hardcode to auto-publish for MVP. Add approval in Phase 2.

### 4. **Notification Templates**
- **Blocker**: Which events trigger notifications? (exam published, result available, approval pending, etc.)
- **Decision needed**: Notification scenarios + template variables.

### 5. **API Controller Layer**
- **Current state**: Minimal API routes.
- **Need**: Standard RESTful endpoints for each domain (Create, Read, Update, Delete).
- **Effort**: ~2 weeks to scaffold all controllers + routes.

---

## 7. RECOMMENDED TECH DECISIONS

### 1. **Authentication Method** (for MVP)
- **Laravel Sanctum** + API tokens (simple, built-in)
- Alternative later: Laravel Passport (OAuth2) for 3rd-party integrations

### 2. **Event Publishing**
- **Laravel Events + Queued Listeners** (for async grading, notifications)
- Ensures exam session doesn't block on grading

### 3. **Caching**
- **Redis** for settings, feature flags, framework cache
- Reduces DB queries, improves response time

### 4. **Testing**
- **Pest** (modern, expressive)
- Test each domain in isolation
- Integration tests for critical paths (exam → grading → result)

### 5. **API Documentation**
- **OpenAPI 3.0 spec** generated from controllers
- Use **Laravel Scramble** to auto-generate Swagger docs

---

## 8. SUMMARY: "WHERE WE ARE" & "WHAT'S NEXT"

### Where We Are:
✅ Multi-tenancy architecture solid (per-database, auto-scoped queries)  
✅ 14 domains modeled (contracts, DTOs, exceptions defined)  
✅ Database schema complete (migrations ready)  
🟡 Service layer 30% done (mostly stubs, little business logic)  
🟡 Repository layer 40% done (interfaces defined, implementations sparse)  
🔴 API/Controller layer 10% done (minimal endpoints)  
🔴 Integration (events, queues) not yet wired

### Golden Path (Next 5 Weeks):
1. **Week 1**: Finish Identity → Candidates can log in
2. **Week 2**: Build Competency + Question → Can define frameworks & questions
3. **Week 3**: Build ExamEngine → Can create & publish exams
4. **Week 4**: Build ExamSession → Candidates can take exams
5. **Week 5**: Build Grading → Results auto-generated

**Then**: Cohorts, Notifications, Analytics (Weeks 6–10)

### Circular Reference Prevention:
- ✅ **Event-driven architecture** (no hard coupling)
- ✅ **Contracts only** (interfaces, not implementations)
- ✅ **One-way dependencies** (enforced direction)
- ✅ **DTOs for cross-domain** (models stay private)
- ✅ **Repository boundaries** (no direct Eloquent between domains)

---

## 9. NEXT ACTION ITEMS

**For you (this week)**:
1. Review Identity domain requirements — confirm auth approach (Sanctum, Passport, JWT?)
2. Decide: Do cohort eligibility rules need custom DSL, or simple config?
3. Decide: Approval workflow needed for MVP, or auto-publish exams?
4. Assign: Which domain implementations start Week 1?

**For the team**:
1. Set up scaffolding for Service/Repository implementations
2. Create test suite structure (Pest)
3. Draft API specification (endpoints, request/response schemas)

---

**Ready to dig into Identity implementation next?**
