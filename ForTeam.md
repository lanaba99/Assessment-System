# ForTeam — مرجع الأدوار والـ Endpoints
## نظام التقييم (Assessment System) — Laravel Multi-Tenant

**تاريخ التوثيق:** 2026-06-15  
**المصدر:** تحليل مباشر لملفات `routes/`, `app/Http/Controllers/`, و`app/Domains/*/Policies/`  
**النطاق:** الأدوار الأربعة — Super Admin، Tenant Admin، Evaluator، Examinee

---

## 1. نظرة عامة على البنية

| الطبقة | ملف المسارات | البادئة | الـ Middleware | قاعدة البيانات |
|--------|-------------|---------|----------------|----------------|
| **Central (Landlord)** | `routes/api.php` | `/api/` | `auth:sanctum`, `central.admin` | Central DB |
| **Tenant** | `routes/tenant.php` | `/api/v1/` | `api`, `InitializeTenancyBySubdomain`, `PreventAccessFromCentralDomains`, `auth:sanctum` | `tenant_<uuid>` |

- **Central:** يُستضاف على النطاق المركزي (مثل `http://localhost`) — **لا** يمر عبر Tenancy.
- **Tenant:** يُستضاف على Subdomain (مثل `http://acme.localhost`) — كل طلب يُفعّل `tenant()` وقاعدة بيانات المستأجر.

### 1.1 مطابقة أسماء الأدوار في الكود

| الدور (تجاري) | التمثيل في الكود | الموديل / الحقل |
|---------------|------------------|-----------------|
| **Super Admin** | مدير المنصة المركزي | `CentralAdminUser` — حقل `is_super_admin` أو `admin_permissions` يحتوي `*` |
| **Tenant Admin** | مدير المستأجر | `User` — `user_type = tenant_admin` + دور **`Super Admin`** (داخل المستأجر) يحمل **كل** الصلاحيات |
| **Evaluator** | المقيّم التقني | دور **`Technical Evaluator`** — `role_category = evaluation` |
| **Examinee** | الممتحن | دور **`Candidate`** — `user_type = examinee`, `role_category = examinee` |

> **ملاحظة مهمة:** داخل المستأجر يوجد دور اسمه `Super Admin` — هذا **ليس** Super Admin المركزي. في البيانات الأولية (`TenantMasterSeeder`) يُمنَح مستخدم `tenant.admin@...` نوع `tenant_admin` ودور `Super Admin` مع جميع الصلاحيات.

---

## 2. تعريف الأدوار

### 2.1 Super Admin (المدير المركزي)

| البُعد | الشرح |
|-------|-------|
| **ما هو؟** | مسؤول المنصة على مستوى **Central** — يدير المستأجرين (Tenants) عبر واجهة Landlord API. |
| **ماذا يفعل؟** | تسجيل الدخول المركزي، إنشاء/عرض/تحديث المستأجرين، مراقبة صحة النظام المركزي (`GET ping`). |
| **لماذا؟** | فصل إدارة المنصة عن بيانات كل مستأجر — يضمن أن عمليات Provisioning لا تتداخل مع سياق Tenancy. |

**Policy:** `CentralTenantPolicy` — كل العمليات (`viewAny`, `view`, `create`, `update`) تتطلب `is_super_admin === true` أو `admin_permissions` يحتوي `*`.

**Middleware:** `EnsureCentralAdmin` — يتحقق أن المستخدم من نوع `CentralAdminUser`.

---

### 2.2 Tenant Admin (مدير المستأجر)

| البُعد | الشرح |
|-------|-------|
| **ما هو؟** | المسؤول الإداري داخل مستأجر واحد — يدير المستخدمين، الأدوار، السياسات الأمنية، المجموعات (Cohorts)، التسجيل في الاختبارات، قواعد العقوبات، ولوحة التحليلات. |
| **ماذا يفعل؟** | إدارة الهوية والوصول (Users/Roles/Security)، تسجيل المرشحين في الاختبارات، إدارة المجموعات، مراقبة الجلسات وإنهاؤها، إدارة قواعد العقوبات وإبطال العقوبات، عرض Analytics. |
| **لماذا؟** | كل مستأجر يحتاج إدارة مستقلة دون الوصول لمستأجرين آخرين — الصلاحيات تُفرَض عبر `AuthorizationService` وPolicies في `app/Domains/`. |

**الصلاحيات الإدارية (من `IdentityPermissionsSeeder`):**

```
users.viewAny | users.view | users.create | users.update | users.deactivate | users.resetPassword
roles.viewAny | roles.view | roles.create | roles.update | roles.delete | roles.assign
security_policies.view | security_policies.update
cohorts.view | cohorts.manage | cohorts.members.manage
exam_sessions.view | exam_sessions.manage
penalties.view | penalties.manage
analytics.view
workflows.manage | workflows.approve
grading.view | grading.publish
```

> في البيانات الأولية، دور `Super Admin` **داخل المستأجر** يحصل على **جميع** الصلاحيات المُعرَّفة في `TenantMasterSeeder` / `IdentityPermissionsSeeder`.

---

### 2.3 Evaluator (المقيّم / المنشئ)

| البُعد | الشرح |
|-------|-------|
| **ما هو؟** | خبير تقييم مسؤول عن **بنك الأسئلة**، **إطار الكفاءات**، **قوالب الاختبارات**، **التصحيح اليدوي**، **نشر النتائج**، و**سير عمل الموافقات**. |
| **ماذا يفعل؟** | إنشاء/تعديل الأسئلة والفئات والكفاءات، بناء ونشر الاختبارات، تقييم الإجابات المفتوحة، نشر النتائج بعد الموافقة، بدء/الموافقة على Workflows. |
| **لماذا؟** | فصل **إنشاء المحتوى والتقييم** عن **الإدارة التشغيلية** (Tenant Admin) وعن **أداء الاختبار** (Examinee). |

**الصلاحيات في البيانات الأولية (`Technical Evaluator`):**

```
grading.evaluate
questions.manage
```

> **تنبيه:** Policies تتطلب صلاحيات إضافية لبعض العمليات (مثل `categories.manage`, `competencies.manage`, `exams.manage`, `grading.publish`, `workflows.manage`). يجب تعيينها للدور عبر `RoleController` أو Seeder لتمكين المقيّم من الوصول الكامل.

---

### 2.4 Examinee (الممتحن / Candidate)

| البُعد | الشرح |
|-------|-------|
| **ما هو؟** | المرشح الذي **يؤدي** الاختبار — `user_type = examinee`، دور `Candidate`. |
| **ماذا يفعل؟** | تسجيل الدخول، بدء جلسة اختبار مُسجَّل فيها، الإجابة على الأسئلة، إرسال أحداث المراقبة (Proctoring)، إكمال/تعليق/استئناف الجلسة، عرض النتيجة بعد النشر. |
| **لماذا؟** | أقل امتيازات ممكنة — يرى جلساته فقط (`ExamSessionPolicy::ownsSession`) ولا يصل لبيانات إدارية أو مفاتيح الإجابات. |

**الصلاحيات في البيانات الأولية (`Candidate`):**

```
exams.view
```

> **تنبيه:** `ExamSessionPolicy::start` يتطلب `exam_sessions.start` — **غير مُمنَح** لدور `Candidate` في `TenantMasterSeeder`. يجب إضافة هذه الصلاحية للممتحنين في الإنتاج.

---

## 3. خريطة الصلاحيات — Policies

### 3.1 Identity Domain

| Policy | Method | الصلاحية المطلوبة | ملاحظات |
|--------|--------|-------------------|---------|
| `UserPolicy` | `viewAny` | `users.viewAny` | |
| | `view` | `users.view` أو نفس المستخدم | |
| | `create` | `users.create` | |
| | `update` | `users.update` | |
| | `deactivate` | `users.deactivate` | لا self-deactivate |
| | `resetPassword` | `users.resetPassword` | |
| `RolePolicy` | `viewAny` | `roles.viewAny` | |
| | `view` | `roles.view` | |
| | `create` | `roles.create` | |
| | `update` | `roles.update` | ممنوع إذا `is_system_role` |
| | `delete` | `roles.delete` | ممنوع إذا `is_system_role` |
| | `assignToUser` | `roles.assign` | |
| `SecurityPolicyPolicy` | `view` | `security_policies.view` | |
| | `update` | `security_policies.update` | |

### 3.2 QuestionBank Domain

| Policy | Method | الصلاحية |
|--------|--------|----------|
| `CategoryPolicy` | `viewAny`, `create`, `update`, `delete` | `categories.manage` |
| `QuestionPolicy` | `viewAny`, `view`, `create`, `update`, `delete` | `questions.manage` |

### 3.3 Competency Domain

| Policy | Method | الصلاحية |
|--------|--------|----------|
| `CompetencyPolicy` | `viewAny`, `create`, `update`, `delete` | `competencies.manage` |

### 3.4 ExamEngine Domain

| Policy | Method | الصلاحية |
|--------|--------|----------|
| `ExamPolicy` | `viewAny`, `view` | `exams.view` |
| | `create`, `update`, `delete` | `exams.manage` |

> `POST exams/{id}/publish` و`POST exams/{id}/archive` يستدعيان `ExamController` → `authorize('update')` → **`exams.manage`** (وليس `exams.publish` رغم وجودها في Seeder).

### 3.5 ExamSession Domain

| Policy | Method | الصلاحية | ملاحظات |
|--------|--------|----------|---------|
| `ExamSessionPolicy` | `start` | `exam_sessions.start` | |
| | `view` | ملكية الجلسة **أو** `exam_sessions.view` | |
| | `participate` | ملكية الجلسة **أو** `exam_sessions.manage` | submit/suspend/resume/complete/heartbeat |
| | `manage` | `exam_sessions.manage` | terminate |
| | `manageEnrollments` | `exam_sessions.manage` | Gate: `manage-enrollments` |

### 3.6 Grading Domain

| Policy | Method | الصلاحية |
|--------|--------|----------|
| `GradingPolicy` | `listPending` | `grading.evaluate` **أو** `grading.view` |
| | `viewEvaluation` | `grading.evaluate` **أو** `grading.view` |
| | `submitEvaluation` | `grading.evaluate` |
| | `publishResult` | `grading.publish` |
| | `viewPublicationStatus` | `grading.view` **أو** `grading.publish` |

### 3.7 Penalties Domain

| Policy | Method | الصلاحية |
|--------|--------|----------|
| `PenaltyPolicy` | `viewAny`, `view`, `viewSanctions` | `penalties.view` **أو** `penalties.manage` |
| | `create`, `update`, `delete`, `voidSanction` | `penalties.manage` |

### 3.8 Workflows Domain

| Policy | Method | الصلاحية |
|--------|--------|----------|
| `ApprovalWorkflowPolicy` | `initiate` | `workflows.manage` |
| | `view` | `workflows.manage` **أو** `workflows.approve` |
| | `approve` | `workflows.approve` |

### 3.9 Analytics Domain

| Policy | Method | الصلاحية |
|--------|--------|----------|
| `AnalyticsPolicy` | `viewDashboard`, `view` | `analytics.view` |

### 3.10 Cohorts Domain

| Policy | Method | الصلاحية |
|--------|--------|----------|
| `CohortPolicy` | `viewAny`, `view` | `cohorts.view` |
| | `create`, `update`, `delete` | `cohorts.manage` |
| | `manageMembers` | `cohorts.members.manage` |

### 3.11 Central Domain

| Policy | Method | الشرط |
|--------|--------|-------|
| `CentralTenantPolicy` | `viewAny`, `view`, `create`, `update` | `CentralAdminUser.is_super_admin` أو `admin_permissions` يحتوي `*` |

---

## 4. تدفقات العمل (User Flows)

### 4.1 Super Admin — تدفق إدارة المستأجرين

```
1. GET  /api/ping
   → Controller: (closure) — فحص صحة Central API

2. POST /api/v1/admin/auth/login
   → Controller: Central\AuthController::login
   → Service:   CentralAuthService::login
   → Response:  Bearer token (Sanctum) + admin_user_id

3. GET  /api/v1/admin/tenants
   → Middleware: auth:sanctum + central.admin
   → Controller: Central\TenantController::index
   → Policy:     CentralTenantPolicy@viewAny
   → Service:    TenantManagementService::list

4. POST /api/v1/admin/tenants
   → Controller: Central\TenantController::store
   → Request:    StoreTenantRequest (authorize: create Tenant)
   → Policy:     CentralTenantPolicy@create
   → Service:    TenantManagementService::create
   → ينشئ Tenant + subdomain + قاعدة بيانات tenant_<uuid>

5. GET  /api/v1/admin/tenants/{tenantId}
   → Controller: Central\TenantController::show
   → Policy:     CentralTenantPolicy@view

6. PATCH /api/v1/admin/tenants/{tenantId}
   → Controller: Central\TenantController::update
   → Request:    UpdateTenantRequest (authorize: update Tenant)
   → Service:    TenantManagementService::update
```

---

### 4.2 Tenant Admin — تدفق إدارة المستخدمين والتسجيل

```
1. POST https://{subdomain}/api/v1/auth/login
   → Controller: Identity\AuthController::login
   → Service:    AuthenticationService::attemptLogin
   → Response:   token (+ MFA challenge إن مُفعَّل)

2. GET  /api/v1/identity/permissions
   → Controller: Identity\IdentityController::permissions
   → Service:    AuthorizationService::listPermissionNamesForUser

3. POST /api/v1/users/invite
   → Controller: Identity\UserController::invite
   → Policy:     UserPolicy@create (users.create)
   → Service:    UserManagementService

4. POST /api/v1/users/{userId}/deactivate
   → Controller: Identity\UserController::deactivate
   → Policy:     UserPolicy@deactivate

5. POST /api/v1/roles/{roleId}/users/{userId}
   → Controller: Identity\RoleController::assignToUser
   → Policy:     RolePolicy@assignToUser

6. GET/PATCH /api/v1/security/policies
   → Controller: Identity\SecurityController
   → Policy:     SecurityPolicyPolicy@view / @update
   → Service:    SecurityPolicyServiceImpl

7. POST /api/v1/exams/{examId}/enrollments
   → Controller: ExamSession\EnrollmentController::store
   → Policy:     ExamSessionPolicy@manageEnrollments (exam_sessions.manage)
   → Service:    EnrollmentServiceImpl::enroll

8. GET  /api/v1/cohorts
   → Controller: Cohorts\CohortController::index
   → Policy:     CohortPolicy@viewAny (cohorts.view)

9. POST /api/v1/penalty-rules
   → Controller: Penalties\PenaltyRuleController::store
   → Request:    StorePenaltyRuleRequest (Penalties.manage)
   → Service:    PenaltyRuleManagementService::create

10. GET /api/v1/analytics/dashboard
    → Controller: Analytics\AnalyticsDashboardController::summary
    → Policy:     AnalyticsPolicy@viewDashboard (analytics.view)
    → Service:    AnalyticsIngestionService::getDashboardSummary

11. POST /api/v1/exam-sessions/{sessionId}/terminate
    → Controller: ExamSession\ExamSessionController::terminate
    → Policy:     ExamSessionPolicy@manage (exam_sessions.manage)
    → Service:    ExamSessionServiceImpl::terminateSession
```

---

### 4.3 Evaluator — تدفق إنشاء الاختبار والتصحيح

```
─── A. بناء المحتوى ───

1. POST /api/v1/categories
   → Controller: QuestionBank\CategoryController::store
   → Policy:     CategoryPolicy@create (categories.manage)
   → Service:    CategoryManagementService

2. POST /api/v1/questions
   → Controller: QuestionBank\QuestionController::store
   → Policy:     QuestionPolicy@create (questions.manage)
   → Service:    QuestionManagementService::createQuestion

3. POST /api/v1/competencies
   → Controller: Competency\CompetencyController::store
   → Policy:     CompetencyPolicy@create (competencies.manage)

4. POST /api/v1/exams
   → Controller: ExamEngine\ExamController::store
   → Policy:     ExamPolicy@create (exams.manage)
   → Service:    ExamEngineService::createExam

5. POST /api/v1/exams/{examId}/publish
   → Controller: ExamEngine\ExamController::publish
   → Policy:     ExamPolicy@update (exams.manage)
   → Service:    ExamEngineService::publishExam

─── B. التصحيح اليدوي ───

6. GET /api/v1/exam-sessions/{sessionId}/pending-evaluations
   → Controller: Grading\ManualEvaluationController::pending
   → Policy:     GradingPolicy@listPending (grading.evaluate | grading.view)
   → Service:    ManualEvaluationServiceImpl::listPendingForSession

7. PATCH /api/v1/answer-evaluations/{evaluationId}/score
   → Controller: Grading\ManualEvaluationController::score
   → Request:    SubmitEvaluationRequest (GradingPolicy@submitEvaluation)
   → Service:    ManualEvaluationServiceImpl::submitScore
   → Body:       score_awarded, rubric_id, evaluator_comments, ...

─── C. نشر النتيجة ───

8. POST /api/v1/workflows
   → Controller: Workflows\ApprovalWorkflowController::initiate
   → Request:    InitiateWorkflowRequest (workflows.manage)
   → Service:    ApprovalWorkflowService::initiate

9. POST /api/v1/workflows/{workflowId}/approve
   → Controller: Workflows\ApprovalWorkflowController::approve
   → Request:    ApproveWorkflowRequest (workflows.approve)
   → Service:    ApprovalWorkflowService::approve

10. POST /api/v1/exam-sessions/{sessionId}/result/publish
    → Controller: Grading\ResultPublicationController::publish
    → Request:    PublishResultRequest (GradingPolicy@publishResult → grading.publish)
    → Service:    ResultPublicationService::publishSessionResult
    → يتحقق من: finalized grade + penalties processed + workflow approved
```

---

### 4.4 Examinee — تدفق أداء الاختبار

```
1. POST /api/v1/auth/login
   → Controller: Identity\AuthController::login
   → Service:    AuthenticationService::attemptLogin
   → (اختياري) POST /api/v1/auth/mfa/verify → verifyMfaForSession

2. GET  /api/v1/exams
   → Controller: ExamEngine\ExamController::index
   → Policy:     ExamPolicy@viewAny (exams.view)
   → Service:    ExamEngineService::listExams

3. POST /api/v1/exam-sessions
   → Controller: ExamSession\ExamSessionController::start
   → Request:    StartSessionRequest (ExamSessionPolicy@start → exam_sessions.start)
   → Service:    ExamSessionServiceImpl::startSession
   → يمر عبر:   EligibilityEvaluatorService (Rules Domain)
   → Gates:      exam published → enrollment exists → window open → attempts → cohort

4. GET  /api/v1/exam-sessions/{sessionId}
   → Controller: ExamSession\ExamSessionController::show
   → Policy:     ExamSessionPolicy@view (ملكية الجلسة)
   → Service:    ExamSessionServiceImpl::getSession
   → Resource:   ExamSessionResource (أسئلة عبر QuestionCandidateResource — بدون مفاتيح إجابة)

5. POST /api/v1/exam-sessions/{sessionId}/responses
   → Controller: ExamSession\ExamSessionController::submitResponse
   → Policy:     ExamSessionPolicy@participate
   → Service:    ExamSessionServiceImpl::submitResponse
   → Event:      ResponseSubmitted

6. POST /api/v1/exam-sessions/{sessionId}/heartbeat
   → Controller: ExamSession\ExamSessionController::heartbeat
   → Policy:     ExamSessionPolicy@participate
   → Service:    ExamSessionServiceImpl::recordHeartbeat

7. POST /api/v1/exam-sessions/{sessionId}/proctor-events
   → Controller: Proctoring\ProctorEventController::store
   → Request:    LogProctorEventRequest (authenticated user فقط)
   → Service:    ProctoringServiceImpl::logEvent

8. POST /api/v1/exam-sessions/{sessionId}/complete
   → Controller: ExamSession\ExamSessionController::complete
   → Policy:     ExamSessionPolicy@participate
   → Service:    ExamSessionServiceImpl::completeSession
   → Event:      ExamSessionCompleted → Grading pipeline

9. GET  /api/v1/exam-sessions/{sessionId}/result
   → Controller: AssessmentResultController::index
   → Service:    AssessmentResultServiceImpl::getForSession
   → ⚠️ لا Policy بعد (TODO Phase C) — tenant isolation فقط
   → النتيجة تظهر للممتحن بعد publication_status = published
```

---

## 5. خريطة الـ Endpoints حسب الدور

### 5.0 Endpoints عامة — بدون مصادقة (Tenant)

| Method | Route | Route Name | Controller::Method | ملاحظات |
|--------|-------|------------|---------------------|---------|
| POST | `/api/v1/auth/login` | `api.v1.auth.login` | `Identity\AuthController::login` | throttle.login |
| POST | `/api/v1/auth/mfa/verify` | `api.v1.auth.mfa.verify` | `Identity\AuthController::verifyMfa` | |
| POST | `/api/v1/auth/password/forgot` | `api.v1.auth.password.forgot` | `Identity\AuthController::forgotPassword` | |
| POST | `/api/v1/auth/password/reset` | `api.v1.auth.password.reset` | `Identity\AuthController::resetPassword` | |
| POST | `/api/v1/auth/accept-invite` | `api.v1.auth.accept-invite` | `Identity\AuthController::acceptInvite` | |
| GET | `/api/v1/system/status` | `api.v1.system.status` | `Identity\SystemController::status` | |

### 5.0b Endpoints عامة — بدون مصادقة (Central)

| Method | Route | Route Name | Controller::Method |
|--------|-------|------------|---------------------|
| GET | `/api/ping` | `api.central.ping` | (closure) |
| POST | `/api/v1/admin/auth/login` | `api.central.admin.login` | `Central\AuthController::login` |

### 5.0c Endpoints عامة — أي مستخدم مصادق (Tenant)

| Method | Route | Route Name | Controller::Method | Policy / Gate |
|--------|-------|------------|---------------------|---------------|
| POST | `/api/v1/auth/logout` | `api.v1.auth.logout` | `Identity\AuthController::logout` | مصادقة فقط |
| POST | `/api/v1/auth/refresh` | `api.v1.auth.refresh` | `Identity\AuthController::refresh` | مصادقة فقط |
| GET | `/api/v1/identity/profile` | `api.v1.identity.profile.show` | `Identity\IdentityController::profile` | مصادقة فقط |
| PATCH | `/api/v1/identity/profile` | `api.v1.identity.profile.update` | `Identity\IdentityController::updateProfile` | self-update |
| GET | `/api/v1/identity/permissions` | `api.v1.identity.permissions.index` | `Identity\IdentityController::permissions` | مصادقة فقط |
| GET | `/api/v1/identity/sessions` | `api.v1.identity.sessions.index` | `Identity\IdentityController::sessions` | مصادقة فقط |
| DELETE | `/api/v1/identity/sessions/{id}` | `api.v1.identity.sessions.delete` | `Identity\IdentityController::deleteSession` | مصادقة فقط |
| DELETE | `/api/v1/identity/sessions/all` | `api.v1.identity.sessions.delete-all` | `Identity\IdentityController::deleteAllSessions` | مصادقة فقط |
| GET | `/api/v1/users/{userId}` | `api.v1.users.show` | `Identity\UserController::show` | `UserPolicy@view` (self أو users.view) |

---

### 5.1 Super Admin — Endpoints خاصة (Central)

| Method | Route | Route Name | Controller::Method | Policy |
|--------|-------|------------|---------------------|--------|
| GET | `/api/v1/admin/tenants` | `api.central.tenants.index` | `Central\TenantController::index` | `CentralTenantPolicy@viewAny` |
| POST | `/api/v1/admin/tenants` | `api.central.tenants.store` | `Central\TenantController::store` | `CentralTenantPolicy@create` |
| GET | `/api/v1/admin/tenants/{tenantId}` | `api.central.tenants.show` | `Central\TenantController::show` | `CentralTenantPolicy@view` |
| PATCH | `/api/v1/admin/tenants/{tenantId}` | `api.central.tenants.update` | `Central\TenantController::update` | `CentralTenantPolicy@update` |

> Super Admin المركزي **لا** يصل لـ Tenant API — يعمل حصرياً على Central host.

---

### 5.2 Tenant Admin — Endpoints خاصة

#### Identity & Access

| Method | Route | Route Name | Controller::Method | Policy / Permission |
|--------|-------|------------|---------------------|---------------------|
| POST | `/api/v1/users` | `api.v1.users.store` | `Identity\UserController::store` | `users.create` |
| GET | `/api/v1/users` | `api.v1.users.index` | `Identity\UserController::index` | `users.viewAny` |
| POST | `/api/v1/users/invite` | `api.v1.users.invite` | `Identity\UserController::invite` | `users.create` |
| POST | `/api/v1/users/{userId}/reset-password` | `api.v1.users.reset-password` | `Identity\UserController::resetPassword` | `users.resetPassword` |
| POST | `/api/v1/users/{userId}/deactivate` | `api.v1.users.deactivate` | `Identity\UserController::deactivate` | `users.deactivate` |
| GET | `/api/v1/roles/` | `api.v1.roles.index` | `Identity\RoleController::index` | `roles.viewAny` |
| POST | `/api/v1/roles/` | `api.v1.roles.store` | `Identity\RoleController::store` | `roles.create` |
| PATCH | `/api/v1/roles/{roleId}` | `api.v1.roles.update` | `Identity\RoleController::update` | `roles.update` |
| DELETE | `/api/v1/roles/{roleId}` | `api.v1.roles.destroy` | `Identity\RoleController::destroy` | `roles.delete` |
| POST | `/api/v1/roles/{roleId}/users/{userId}` | `api.v1.roles.assign` | `Identity\RoleController::assignToUser` | `roles.assign` |
| DELETE | `/api/v1/roles/{roleId}/users/{userId}` | `api.v1.roles.unassign` | `Identity\RoleController::removeFromUser` | `roles.assign` |
| GET | `/api/v1/security/policies` | `api.v1.security.policies.show` | `Identity\SecurityController::policies` | `security_policies.view` |
| PATCH | `/api/v1/security/policies` | `api.v1.security.policies.update` | `Identity\SecurityController::updatePolicies` | `security_policies.update` |

#### Cohorts

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| GET | `/api/v1/cohorts` | `api.v1.cohorts.index` | `Cohorts\CohortController::index` | `cohorts.view` |
| POST | `/api/v1/cohorts` | `api.v1.cohorts.store` | `Cohorts\CohortController::store` | `cohorts.manage` |
| GET | `/api/v1/cohorts/{cohortId}` | `api.v1.cohorts.show` | `Cohorts\CohortController::show` | `cohorts.view` |
| PATCH | `/api/v1/cohorts/{cohortId}` | `api.v1.cohorts.update` | `Cohorts\CohortController::update` | `cohorts.manage` |
| DELETE | `/api/v1/cohorts/{cohortId}` | `api.v1.cohorts.destroy` | `Cohorts\CohortController::destroy` | `cohorts.manage` |
| GET | `/api/v1/cohorts/{cohortId}/members` | `api.v1.cohorts.members.index` | `Cohorts\CohortMemberController::index` | `cohorts.view` |
| POST | `/api/v1/cohorts/{cohortId}/members` | `api.v1.cohorts.members.store` | `Cohorts\CohortMemberController::store` | `cohorts.members.manage` |
| DELETE | `/api/v1/cohorts/{cohortId}/members/{userId}` | `api.v1.cohorts.members.destroy` | `Cohorts\CohortMemberController::destroy` | `cohorts.members.manage` |

#### Exam Enrollments

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| GET | `/api/v1/exams/{examId}/enrollments` | `api.v1.enrollments.index` | `ExamSession\EnrollmentController::index` | `exam_sessions.manage` |
| POST | `/api/v1/exams/{examId}/enrollments` | `api.v1.enrollments.store` | `ExamSession\EnrollmentController::store` | `exam_sessions.manage` |
| DELETE | `/api/v1/exams/{examId}/enrollments/{enrollmentId}` | `api.v1.enrollments.destroy` | `ExamSession\EnrollmentController::destroy` | `exam_sessions.manage` |

#### Session Management (Admin/Proctor)

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| GET | `/api/v1/exam-sessions/{sessionId}` | `api.v1.exam-sessions.show` | `ExamSession\ExamSessionController::show` | `exam_sessions.view` |
| POST | `/api/v1/exam-sessions/{sessionId}/terminate` | `api.v1.exam-sessions.terminate` | `ExamSession\ExamSessionController::terminate` | `exam_sessions.manage` |
| GET | `/api/v1/exam-sessions/{sessionId}/proctor-events` | `api.v1.exam-sessions.proctor-events.index` | `Proctoring\ProctorEventController::index` | ⚠️ **لا Policy** — أي مصادق |
| GET | `/api/v1/exam-sessions/{sessionId}/sanctions` | `api.v1.exam-sessions.sanctions.index` | `Penalties\PenaltySanctionController::index` | `penalties.view` أو `penalties.manage` |
| POST | `/api/v1/sanctions/{sanctionId}/void` | `api.v1.sanctions.void` | `Penalties\PenaltySanctionController::void` | `penalties.manage` |

#### Penalty Rules

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| GET | `/api/v1/penalty-rules` | `api.v1.penalty-rules.index` | `Penalties\PenaltyRuleController::index` | `penalties.view` أو `penalties.manage` |
| POST | `/api/v1/penalty-rules` | `api.v1.penalty-rules.store` | `Penalties\PenaltyRuleController::store` | `penalties.manage` |
| GET | `/api/v1/penalty-rules/{ruleId}` | `api.v1.penalty-rules.show` | `Penalties\PenaltyRuleController::show` | `penalties.view` أو `penalties.manage` |
| PATCH | `/api/v1/penalty-rules/{ruleId}` | `api.v1.penalty-rules.update` | `Penalties\PenaltyRuleController::update` | `penalties.manage` |
| DELETE | `/api/v1/penalty-rules/{ruleId}` | `api.v1.penalty-rules.destroy` | `Penalties\PenaltyRuleController::destroy` | `penalties.manage` |
| POST | `/api/v1/penalty-rules/{ruleId}/activate` | `api.v1.penalty-rules.activate` | `Penalties\PenaltyRuleController::activate` | `penalties.manage` |
| POST | `/api/v1/penalty-rules/{ruleId}/deactivate` | `api.v1.penalty-rules.deactivate` | `Penalties\PenaltyRuleController::deactivate` | `penalties.manage` |

#### Analytics & Grading Oversight

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| GET | `/api/v1/analytics/dashboard` | `api.v1.analytics.dashboard` | `Analytics\AnalyticsDashboardController::summary` | `analytics.view` |
| GET | `/api/v1/exam-sessions/{sessionId}/result/publication-status` | `api.v1.exam-sessions.result.publication-status` | `Grading\ResultPublicationController::showPublicationStatus` | `grading.view` أو `grading.publish` |
| POST | `/api/v1/exam-sessions/{sessionId}/result/publish` | `api.v1.exam-sessions.result.publish` | `Grading\ResultPublicationController::publish` | `grading.publish` |
| GET | `/api/v1/exam-sessions/{sessionId}/result` | `api.v1.exam-sessions.result` | `AssessmentResultController::index` | ⚠️ TODO Policy |

#### Workflows (إذا مُمنَح للمدير)

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| POST | `/api/v1/workflows` | `api.v1.workflows.initiate` | `Workflows\ApprovalWorkflowController::initiate` | `workflows.manage` |
| GET | `/api/v1/workflows/{workflowId}` | `api.v1.workflows.show` | `Workflows\ApprovalWorkflowController::show` | `workflows.manage` أو `workflows.approve` |
| POST | `/api/v1/workflows/{workflowId}/approve` | `api.v1.workflows.approve` | `Workflows\ApprovalWorkflowController::approve` | `workflows.approve` |

> Tenant Admin بدور `Super Admin` داخل المستأجر يحصل أيضاً على **جميع** Endpoints الخاصة بالـ Evaluator (القسم 5.3) لأن Seeder يمنحه كل الصلاحيات.

---

### 5.3 Evaluator — Endpoints خاصة

#### Question Bank

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| GET | `/api/v1/categories/tree` | `api.v1.categories.tree` | `QuestionBank\CategoryController::tree` | `categories.manage` |
| POST | `/api/v1/categories` | `api.v1.categories.store` | `QuestionBank\CategoryController::store` | `categories.manage` |
| PATCH | `/api/v1/categories/{id}/move` | `api.v1.categories.move` | `QuestionBank\CategoryController::move` | `categories.manage` |
| DELETE | `/api/v1/categories/{id}` | `api.v1.categories.destroy` | `QuestionBank\CategoryController::destroy` | `categories.manage` |
| GET | `/api/v1/questions` | `api.v1.questions.index` | `QuestionBank\QuestionController::index` | `questions.manage` |
| POST | `/api/v1/questions` | `api.v1.questions.store` | `QuestionBank\QuestionController::store` | `questions.manage` |
| GET | `/api/v1/questions/{id}` | `api.v1.questions.show` | `QuestionBank\QuestionController::show` | `questions.manage` |
| PUT/PATCH | `/api/v1/questions/{id}` | `api.v1.questions.update` | `QuestionBank\QuestionController::update` | `questions.manage` |
| DELETE | `/api/v1/questions/{id}` | `api.v1.questions.destroy` | `QuestionBank\QuestionController::destroy` | `questions.manage` |

#### Competency Framework

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| GET | `/api/v1/competencies/tree` | `api.v1.competencies.tree` | `Competency\CompetencyController::tree` | `competencies.manage` |
| POST | `/api/v1/competencies` | `api.v1.competencies.store` | `Competency\CompetencyController::store` | `competencies.manage` |
| PATCH | `/api/v1/competencies/{id}/move` | `api.v1.competencies.move` | `Competency\CompetencyController::move` | `competencies.manage` |
| DELETE | `/api/v1/competencies/{id}` | `api.v1.competencies.destroy` | `Competency\CompetencyController::destroy` | `competencies.manage` |

#### Exam Engine

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| GET | `/api/v1/exams` | `api.v1.exams.index` | `ExamEngine\ExamController::index` | `exams.view` |
| POST | `/api/v1/exams` | `api.v1.exams.store` | `ExamEngine\ExamController::store` | `exams.manage` |
| GET | `/api/v1/exams/{examId}` | `api.v1.exams.show` | `ExamEngine\ExamController::show` | `exams.view` |
| PATCH | `/api/v1/exams/{examId}` | `api.v1.exams.update` | `ExamEngine\ExamController::update` | `exams.manage` |
| DELETE | `/api/v1/exams/{examId}` | `api.v1.exams.destroy` | `ExamEngine\ExamController::destroy` | `exams.manage` |
| POST | `/api/v1/exams/{examId}/publish` | `api.v1.exams.publish` | `ExamEngine\ExamController::publish` | `exams.manage` |
| POST | `/api/v1/exams/{examId}/archive` | `api.v1.exams.archive` | `ExamEngine\ExamController::archive` | `exams.manage` |

#### Manual Grading

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| GET | `/api/v1/exam-sessions/{sessionId}/pending-evaluations` | `api.v1.exam-sessions.pending-evaluations` | `Grading\ManualEvaluationController::pending` | `grading.evaluate` أو `grading.view` |
| PATCH | `/api/v1/answer-evaluations/{evaluationId}/score` | `api.v1.answer-evaluations.score` | `Grading\ManualEvaluationController::score` | `grading.evaluate` |

#### Result Publication & Workflows

| Method | Route | Route Name | Controller::Method | Permission |
|--------|-------|------------|---------------------|------------|
| POST | `/api/v1/exam-sessions/{sessionId}/result/publish` | `api.v1.exam-sessions.result.publish` | `Grading\ResultPublicationController::publish` | `grading.publish` |
| GET | `/api/v1/exam-sessions/{sessionId}/result/publication-status` | `api.v1.exam-sessions.result.publication-status` | `Grading\ResultPublicationController::showPublicationStatus` | `grading.view` أو `grading.publish` |
| POST | `/api/v1/workflows` | `api.v1.workflows.initiate` | `Workflows\ApprovalWorkflowController::initiate` | `workflows.manage` |
| GET | `/api/v1/workflows/{workflowId}` | `api.v1.workflows.show` | `Workflows\ApprovalWorkflowController::show` | `workflows.manage` أو `workflows.approve` |
| POST | `/api/v1/workflows/{workflowId}/approve` | `api.v1.workflows.approve` | `Workflows\ApprovalWorkflowController::approve` | `workflows.approve` |

---

### 5.4 Examinee — Endpoints خاصة

| Method | Route | Route Name | Controller::Method | Policy / Permission |
|--------|-------|------------|---------------------|---------------------|
| GET | `/api/v1/exams` | `api.v1.exams.index` | `ExamEngine\ExamController::index` | `exams.view` |
| GET | `/api/v1/exams/{examId}` | `api.v1.exams.show` | `ExamEngine\ExamController::show` | `exams.view` |
| POST | `/api/v1/exam-sessions` | `api.v1.exam-sessions.start` | `ExamSession\ExamSessionController::start` | `exam_sessions.start` |
| GET | `/api/v1/exam-sessions/{sessionId}` | `api.v1.exam-sessions.show` | `ExamSession\ExamSessionController::show` | ملكية الجلسة |
| POST | `/api/v1/exam-sessions/{sessionId}/responses` | `api.v1.exam-sessions.submit-response` | `ExamSession\ExamSessionController::submitResponse` | `participate` (ملكية) |
| POST | `/api/v1/exam-sessions/{sessionId}/suspend` | `api.v1.exam-sessions.suspend` | `ExamSession\ExamSessionController::suspend` | `participate` |
| POST | `/api/v1/exam-sessions/{sessionId}/resume` | `api.v1.exam-sessions.resume` | `ExamSession\ExamSessionController::resume` | `participate` |
| POST | `/api/v1/exam-sessions/{sessionId}/complete` | `api.v1.exam-sessions.complete` | `ExamSession\ExamSessionController::complete` | `participate` |
| POST | `/api/v1/exam-sessions/{sessionId}/heartbeat` | `api.v1.exam-sessions.heartbeat` | `ExamSession\ExamSessionController::heartbeat` | `participate` |
| POST | `/api/v1/exam-sessions/{sessionId}/proctor-events` | `api.v1.exam-sessions.proctor-events.store` | `Proctoring\ProctorEventController::store` | مصادقة فقط |
| GET | `/api/v1/exam-sessions/{sessionId}/result` | `api.v1.exam-sessions.result` | `AssessmentResultController::index` | tenant isolation + publication gate |

---

## 6. Services الرئيسية المستخدمة في التدفقات

| Domain | Service | Controller(s) التي تستدعيه |
|--------|---------|---------------------------|
| Central | `CentralAuthService` | `Central\AuthController` |
| Central | `TenantManagementService` | `Central\TenantController` |
| Identity | `AuthenticationService` | `Identity\AuthController` |
| Identity | `AuthorizationService` | Policies + `IdentityController` |
| Identity | `UserManagementService` | `Identity\UserController`, `IdentityController` |
| Identity | `SecurityPolicyServiceImpl` | `Identity\SecurityController` |
| QuestionBank | `QuestionManagementService` | `QuestionBank\QuestionController` |
| ExamEngine | `ExamEngineService` | `ExamEngine\ExamController` |
| ExamSession | `ExamSessionServiceImpl` | `ExamSession\ExamSessionController` |
| ExamSession | `EnrollmentServiceImpl` | `ExamSession\EnrollmentController` |
| Rules | `EligibilityEvaluatorService` | `ExamSessionServiceImpl::startSession` (داخلي) |
| Proctoring | `ProctoringServiceImpl` | `Proctoring\ProctorEventController` |
| Grading | `ManualEvaluationServiceImpl` | `Grading\ManualEvaluationController` |
| Grading | `ResultPublicationService` | `Grading\ResultPublicationController` |
| Grading | `AssessmentResultServiceImpl` | `AssessmentResultController` |
| Penalties | `PenaltyRuleManagementService` | `Penalties\PenaltyRuleController` |
| Penalties | `PenaltySanctionService` | `Penalties\PenaltySanctionController` |
| Workflows | `ApprovalWorkflowService` | `Workflows\ApprovalWorkflowController` |
| Analytics | `AnalyticsIngestionService` | `Analytics\AnalyticsDashboardController` |
| Cohorts | (via Repositories in Controllers) | `Cohorts\CohortController`, `CohortMemberController` |

---

## 7. فجوات وملاحظات تقنية للفريق

| # | الموضوع | التفاصيل |
|---|---------|----------|
| 1 | **Rules Domain بدون HTTP** | `EligibilityEvaluatorService` يعمل داخلياً فقط — لا Routes لـ CRUD قواعد الأهلية. |
| 2 | **`exams.publish` vs `exams.manage`** | الصلاحية `exams.publish` موجودة في Seeder لكن `ExamController::publish` يتحقق من `exams.manage`. |
| 3 | **`exam_sessions.start` للممتحن** | Policy تتطلبها لكن دور `Candidate` في Seeder لا يحملها — يجب إصلاح Seeder. |
| 4 | **`AssessmentResultController`** | TODO Phase C — لا `GradingPolicy` check بعد. |
| 5 | **`ProctorEventController::index`** | لا Policy — أي مستخدم مصادق يمكنه قراءة أحداث المراقبة. |
| 6 | **`proctoring.ingest` / `proctoring.view`** | مُعرَّفة في `IdentityPermissionsSeeder` لكن **لا Policy** مرتبطة بها في `ProctorEventController`. |
| 7 | **تحديث المستخدم** | لا Route `PATCH /users/{id}` — تحديث الملف الشخصي عبر `PATCH /identity/profile` (self فقط). |
| 8 | **دور `Proctor`** | موجود في Seeder (`exam_sessions.start`) — خارج نطاق الأدوار الأربعة لكنه يشارك بعض صلاحيات Tenant Admin على الجلسات. |

---

## 8. ملخص سريع — من يرى ماذا؟

```
Super Admin (Central)
  └── Central API فقط → إدارة Tenants

Tenant Admin (دور Super Admin داخل المستأجر)
  └── Users, Roles, Security, Cohorts, Enrollments, Penalties, Analytics
  └── + كل صلاحيات Evaluator إذا كان الدور يحمل كل Permissions

Evaluator (Technical Evaluator)
  └── Questions, Categories, Competencies, Exams, Manual Grading, Publish Results, Workflows

Examinee (Candidate)
  └── Login → Start Session → Answer → Proctor Events → Complete → View Published Result
```

---

*هذا الملف مُستخرَج من الكود الفعلي في `assessment-system`. عند أي تغيير في Routes أو Policies، يُحدَّث هذا المرجع accordingly.*
