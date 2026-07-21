# API Endpoint Catalogue — v2 (corrected against `lanaba99/Assessment-System`, `main`)

> **قاعدة عامة قبل ما تبلشي:** الكود ما بيتحقق من "الدور" (role name) أبداً — كل الـ Policies بتفحص permission strings محدّدة (`AuthorizationService::userHasPermission()`), والـ permissions هاي معطاة للأدوار عن طريق Seeder (`TenantMasterSeeder`). يعني عمود "Required role" باقي الجدول هو **اتفاقية افتراضية** (مين بيمتلك هاد الـ permission بالإعدادات الافتراضية) مش شي متحقق منه بالاسم. الاستثناء الوحيد: نقاط الـ Central Admin (تينانتس) يلي بتتحقق مباشرة من `is_super_admin` (بولياني، مش permission string).

---

## 🧑‍💼 جدول 1 — كل شي فيه يعمله الـ Proctor

صلاحيات الـ Proctor المزروعة فعلياً بالـ seeder: `exam_sessions.start`, `exam_sessions.view`, `proctoring.view`, `proctoring.ingest`, `penalties.view`.

| Method | Path | Permission يفتحله الوصول | ملاحظة |
|---|---|---|---|
| POST | /api/v1/auth/logout | أي مستخدم مسجّل دخول | |
| POST | /api/v1/auth/refresh | أي مستخدم مسجّل دخول | |
| GET | /api/v1/identity/profile | أي مستخدم مسجّل دخول | |
| PATCH | /api/v1/identity/profile | أي مستخدم مسجّل دخول | تعديل بياناته هو فقط |
| GET | /api/v1/identity/permissions | أي مستخدم مسجّل دخول | |
| GET | /api/v1/identity/sessions | أي مستخدم مسجّل دخول | |
| DELETE | /api/v1/identity/sessions/all | أي مستخدم مسجّل دخول | |
| DELETE | /api/v1/identity/sessions/{id} | أي مستخدم مسجّل دخول | |
| POST | /api/v1/exam-sessions | `exam_sessions.start` | نظرياً مسموحة بالـ Policy، بس منطق الأهلية (enrollment/eligibility) بطبقة الـ Service ممكن يمنعها إذا الـ Proctor مو مسجّل كمرشّح على الامتحان |
| GET | /api/v1/exam-sessions/{sessionId} | `exam_sessions.view` | يقدر يشوف أي جلسة بنفس التينانت |
| POST | /api/v1/exam-sessions/{sessionId}/proctor-events | `proctoring.ingest` | ⚠️ هاي الصلاحية الوحيدة يلي فيها ingest — التعليق بالكود بيقول هاي "صلاحية نظام" للأداة/المتصفح يلي بيراقب الجلسة، مش لأي حساب بشري عادي |
| GET | /api/v1/exam-sessions/{sessionId}/proctor-events | `proctoring.view` | |
| GET | /api/v1/penalty-rules | `penalties.view` | قراءة بس |
| GET | /api/v1/penalty-rules/{ruleId} | `penalties.view` | قراءة بس |
| GET | /api/v1/exam-sessions/{sessionId}/sanctions | `penalties.view` | قراءة بس |

**⛔ اللي ما يقدرش يعمله الـ Proctor (رغم إنه منطقياً متوقع):**
- ما فيه `exam_sessions.manage` → **ما فيه صلاحية suspend / resume / complete / terminate / submit-response** على جلسة حدا ثاني، ولا حتى `heartbeat`. غريب لأنه اسم الدور "Proctor" بيوحي إنه هو يلي بيتدخل بالطوارئ، بس فعلياً الصلاحية يلي بتسمح بالتدخل (`exam_sessions.manage`) معطاة بس لـ Technical Evaluator و Tenant Admin — مو للـ Proctor. هاي نقطة تصميم لازم تتأكدوا منها مع الفريق قبل ما توثقوها للفرونت.
- ما فيه `penalties.manage` → ما يقدر يعمل void لأي sanction، ولا ينشئ/يعدّل قواعد عقوبات.
- ما فيه وصول لنتيجة الجلسة (`GET .../result`) ولا للشهادة (`GET .../certificate`) — هدول مقصورين على صاحب الجلسة نفسه أو حدا عنده `grading.*`.

---

## 🧑‍🏫 جدول 2 — كل شي فيه يعمله الـ Technical Evaluator

صلاحيات الـ Evaluator المزروعة فعلياً: `grading.evaluate`, `questions.manage`, `categories.manage`, `competencies.manage`, `exams.manage`, `exams.publish`, `grading.view`, `grading.publish`, `workflows.manage`, `eligibility.manage`, `eligibility.view`, `exam_sessions.manage`.

| Method | Path | Permission يفتحله الوصول | ملاحظة |
|---|---|---|---|
| POST | /api/v1/auth/logout , /auth/refresh | أي مستخدم مسجّل دخول | |
| GET/PATCH | /api/v1/identity/profile | أي مستخدم مسجّل دخول | |
| GET | /api/v1/identity/permissions , /identity/sessions | أي مستخدم مسجّل دخول | |
| DELETE | /api/v1/identity/sessions/all , /{id} | أي مستخدم مسجّل دخول | |
| POST | /api/v1/exams | `exams.manage` | |
| PATCH | /api/v1/exams/{examId} | `exams.manage` | |
| DELETE | /api/v1/exams/{examId} | `exams.manage` | |
| POST | /api/v1/exams/{examId}/publish | `exams.publish` | |
| POST | /api/v1/exams/{examId}/archive | `exams.manage` | |
| GET | /api/v1/exams/{examId}/results/export | `grading.view` أو `evaluate` أو `publish` | **بعد ما تضيفوا الـ authorize() المفقود بالـ ReportController (شوفي التصليح يلي عطيتك ياه) — قبل التصليح هاد الإندبوينت مفتوح لأي حدا مسجّل دخول** |
| GET | /api/v1/categories/tree , POST /categories , PATCH .../move , DELETE | `categories.manage` | نفس الصلاحية لكل العمليات (قراءة وكتابة) |
| GET | /api/v1/questions , POST /questions , GET /questions/{id} , PATCH/PUT , DELETE , POST /bulk-import | `questions.manage` | نفس الصلاحية لكل العمليات |
| GET | /api/v1/competencies/tree , POST , PATCH .../move , DELETE | `competencies.manage` | نفس الصلاحية لكل العمليات |
| GET | /api/v1/eligibility-chains , POST , GET/{id} , PATCH/{id} , DELETE/{id} | `eligibility.view` أو `eligibility.manage` | |
| GET | /api/v1/exam-sessions/{sessionId}/pending-evaluations | `grading.evaluate` أو `grading.view` | |
| PATCH | /api/v1/answer-evaluations/{evaluationId}/score | `grading.evaluate` | |
| POST | /api/v1/exam-sessions/{sessionId}/result/publish | `grading.publish` | |
| GET | /api/v1/exam-sessions/{sessionId}/result/publication-status | `grading.view` أو `grading.publish` | |
| GET | /api/v1/exam-sessions/{sessionId}/result | `grading.view/evaluate/publish` | يقدر يشوف نتيجة أي جلسة (مش بس المنشورة) |
| POST | /api/v1/workflows | `workflows.manage` | |
| GET | /api/v1/workflows/{workflowId} | `workflows.manage` أو `workflows.approve` | |
| GET | /api/v1/exams/{examId}/enrollments , POST , DELETE/{id} | `exam_sessions.manage` | |
| POST | /api/v1/exam-sessions/{sessionId}/suspend , /resume , /complete , /heartbeat , /responses | `exam_sessions.manage` (أو صاحب الجلسة) | تقنياً مسموحة له لأنه عنده `exam_sessions.manage`، بس هاي أفعال مرشّح أصلاً — تأكدوا مع الفريق هل فعلاً بدكم الـ Evaluator يقدر يسوي submit-response نيابة عن مرشّح |
| POST | /api/v1/exam-sessions/{sessionId}/terminate | `exam_sessions.manage` | هون منطقي إنه Evaluator يقدر ينهي جلسة بالطارئ |

**⛔ اللي ما يقدرش يعمله الـ Evaluator (لقيتها وأنا عم دقق — لازم تتأكدوا منها):**
- **⚠️ `GET /api/v1/exams` و `GET /api/v1/exams/{examId}` — ما عندوش وصول!** `ExamPolicy::viewAny/view` بتفحص `exams.view` بس، والـ Evaluator ماعندوش هاد الـ permission بالـ matrix المزروعة (عندو `exams.manage` و `exams.publish` بس). يعني فعلياً هو يقدر ينشئ/يعدّل/ينشر امتحان، بس ما يقدر يجيبه بـ GET! هاي على الأغلب نسيان بالـ seeder — لازم تضيفوا `exams.view` لصلاحيات Technical Evaluator بالكود، أو تأكدوا إنه مقصود.
- **⚠️ `POST /api/v1/workflows/{workflowId}/approve` — ما عندوش `workflows.approve`**، عندو بس `workflows.manage`. يعني يقدر يبلش (initiate) الـ workflow ويشوفه، بس ما يقدر يوافق (approve) عليه — هاي الصلاحية محصورة بـ Tenant Admin بس.
- ما فيه `cohorts.*`, `users.*`, `roles.*`, `security_policies.*`, `analytics.view` — ولا وصول لأي إندبوينت من هدول.

---

## 🧑‍🎓 جدول 3 — كل شي فيه يعمله الـ Candidate

صلاحيات الـ Candidate المزروعة فعلياً: `exams.view`, `exam_sessions.start` — والباقي كله عن طريق "ownership" (هو صاحب الجلسة/النتيجة/الشهادة).

| Method | Path | مين بيسمحله | ملاحظة |
|---|---|---|---|
| POST | /api/v1/auth/logout , /auth/refresh | أي مستخدم مسجّل دخول | |
| GET/PATCH | /api/v1/identity/profile | أي مستخدم مسجّل دخول | |
| GET | /api/v1/identity/permissions , /identity/sessions | أي مستخدم مسجّل دخول | |
| DELETE | /api/v1/identity/sessions/all , /{id} | أي مستخدم مسجّل دخول | |
| GET | /api/v1/exams | `exams.view` | يشوف قائمة الامتحانات المتاحة |
| GET | /api/v1/exams/{examId} | `exams.view` | |
| POST | /api/v1/exam-sessions | `exam_sessions.start` | (شرط يكون مسجّل/enrolled بالامتحان — بيتفحص بطبقة الـ Service) |
| GET | /api/v1/exam-sessions/{sessionId} | صاحب الجلسة فقط | |
| POST | /api/v1/exam-sessions/{sessionId}/responses | صاحب الجلسة فقط | |
| POST | /api/v1/exam-sessions/{sessionId}/suspend | صاحب الجلسة فقط | |
| POST | /api/v1/exam-sessions/{sessionId}/resume | صاحب الجلسة فقط | |
| POST | /api/v1/exam-sessions/{sessionId}/complete | صاحب الجلسة فقط | |
| POST | /api/v1/exam-sessions/{sessionId}/heartbeat | صاحب الجلسة فقط | |
| GET | /api/v1/exam-sessions/{sessionId}/result | صاحب الجلسة فقط | **وبس إذا كانت النتيجة منشورة (`published`) — قبل هيك بيرجع 404** |
| GET | /api/v1/exam-sessions/{sessionId}/certificate | صاحب الشهادة فقط | تنزيل ملف |

**⛔ اللي ما يقدرش يعمله الـ Candidate (نقاط مهمة تنتبهولها بالفرونت):**
- **⚠️ ما يقدر يسوي `POST /exam-sessions/{sessionId}/proctor-events` بحسابه هو!** هاد الإندبوينت محتاج `proctoring.ingest`، والـ Candidate ماعندوش هاد الـ permission إطلاقاً بالـ matrix المزروعة — بس Proctor عنده. يعني لو تصميمكم إنه متصفح المرشّح نفسه هو يلي بيبعت أحداث المراقبة، لازم يكون فيه حساب/توكن خاص (system/agent) عنده صلاحية Proctor، مش حساب المرشّح العادي. لازم توضحوها مع الباك اند قبل ما الفرونت يبني عليها.
- ما فيه `exam_sessions.manage` → ما يقدر يعمل `terminate` على جلسته ولا جلسة حدا ثاني.
- ما فيه وصول لأي إندبوينت إداري (roles/users/cohorts/questions/exams-management/penalty-rules/...).

---

## 📋 الجدول الكامل — كل الإندبوينتس (106 endpoint، مطابقة للكود بعد التصحيح)

كل التصحيحات محددة بعلامة 🔴 بعمود "Notes" وين صار تغيير جوهري عن نسختك الأولى.

| Method | Path | Required role (اتفاقية، مش متحقق بالاسم) | Required permission | Body (Postman) | Required params | Optional params | Expected response | Notes |
|---|---|---|---|---|---|---|---|---|
| GET | /up | Public | None | None | None | None | 200 OK | 🔴 المسار الصحيح `/up` مش `/api/up` — الـ health check مسجّل بره ملف `api.php` (`bootstrap/app.php: health: '/up'`). |
| GET | /api/ping | Public | None | None | None | None | `{"scope":"central","ok":true}` | Health check للـ central context. |
| POST | /api/v1/admin/auth/login | Central Admin | None | `{"email":"admin@example.com","password":"StrongPassword123!"}` | email, password | None | 200 OK + token | مصادقة central. |
| GET | /api/v1/admin/tenants | Central Admin | 🔴 `is_super_admin` (مش permission string — فحص بولياني مباشر) | None | None | None | 200 OK + قائمة تينانتس | |
| POST | /api/v1/admin/tenants | Central Admin | 🔴 `is_super_admin` | `{"organization_name":"Acme","primary_contact_email":"owner@example.com","domain":"acme"}` | organization_name, primary_contact_email, domain | organization_type, primary_contact_phone, 🔴 deployment_config, feature_flags, max_concurrent_users, max_storage_quota_mb (🔴 شيلي `status` — مو حقل مقبول بالإنشاء) | 201 Created | |
| GET | /api/v1/admin/tenants/{tenantId} | Central Admin | 🔴 `is_super_admin` | None | None | None | 200 OK | |
| PATCH | /api/v1/admin/tenants/{tenantId} | Central Admin | 🔴 `is_super_admin` | `{"organization_name":"Acme Updated"}` | None | organization_name, organization_type, primary_contact_email, 🔴 primary_contact_phone, status, feature_flags, max_concurrent_users, max_storage_quota_mb | 200 OK | `status` قيمها: `active`\|`suspended`\|`inactive`. |
| POST | /api/v1/admin/tenants/{tenantId}/suspend | Central Admin | 🔴 `is_super_admin` | None | None | None | 200 OK | |
| POST | /api/v1/admin/tenants/{tenantId}/reactivate | Central Admin | 🔴 `is_super_admin` | None | None | None | 200 OK | |
| POST | /api/v1/auth/login | Public | None | `{"tenant_id":"<uuid>","email":"candidate@example.com","password":"StrongPassword123!"}` | email, password | tenant_id | 200 OK + token | |
| POST | /api/v1/auth/mfa/verify | Authenticated user | None | `{"session_id":"<uuid>","one_time_code":"123456"}` | 🔴 session_id, one_time_code (مو `mfa_code`) | None | 200 OK | |
| POST | /api/v1/auth/password/forgot | Public | None | `{"email":"user@example.com"}` | email | None | 200 OK | |
| POST | /api/v1/auth/password/reset | Public | None | `{"email":"user@example.com","token":"<reset-token>","password":"NewPassword123!","password_confirmation":"NewPassword123!"}` | 🔴 email, token, password, password_confirmation | None | 200 OK | كلمة السر لازم 12 محرف ع الأقل + كبير/صغير/رقم/رمز. |
| POST | /api/v1/auth/accept-invite | Public | None | `{"email":"user@example.com","token":"<invite-token>","password":"NewPassword123!","password_confirmation":"NewPassword123!"}` | 🔴 email, token, password, password_confirmation | None | 200 OK | |
| GET | /api/v1/system/status | Public | None | None | None | None | 200 OK | |
| GET | /api/v1/certificates/verify/{token} | Public | None | None | None | None | 200 OK | |
| POST | /api/v1/auth/logout | Authenticated user | None | None | None | None | 204 | |
| POST | /api/v1/auth/refresh | Authenticated user | None | None | None | None | 200 OK | |
| POST | /api/v1/users | Tenant Admin | 🔴 `users.create` | `{"email":"new.user@example.com","password":"StrongPassword123!","password_confirmation":"StrongPassword123!","first_name":"New","last_name":"User","user_type":"staff"}` | email, password, password_confirmation, first_name, last_name, user_type | external_employee_id, department_id, user_attributes | 201 Created | كلمة السر: 12+ محرف، كبير/صغير/رقم/رمز، وconfirmed. |
| GET | /api/v1/users | Tenant Admin | 🔴 `users.viewAny` | None | None | per_page | 200 OK | |
| POST | /api/v1/users/invite | Tenant Admin | 🔴 `users.create` | `{"email":"invitee@example.com","first_name":"Invite","last_name":"User","user_type":"staff"}` | email, first_name, last_name, user_type | external_employee_id, department_id, user_attributes | 201 Created | |
| GET | /api/v1/users/{userId} | Tenant Admin أو الشخص نفسه | 🔴 `users.view` (بس فيه bypass حقيقي للشخص نفسه — هاد الوحيد يلي فيه) | None | None | None | 200 OK | |
| PATCH | /api/v1/users/{userId} | 🔴 Tenant Admin فقط (شيلي "أو الشخص نفسه" — مو موجودة فعلياً بالكود) | 🔴 `users.update` (لازم حتى لو عم يعدّل على نفسه) | `{"first_name":"Jane","last_name":"Doe"}` | None | first_name, last_name, external_employee_id, user_type, department_id, status, user_attributes, 🔴 is_active | 200 OK | 🔴 المستخدم العادي يلي بدو يعدّل بياناته هو لازم يستخدم `PATCH /identity/profile` بدل هاي. |
| POST | /api/v1/users/{userId}/reset-password | 🔴 Tenant Admin فقط (شيلي "أو الشخص نفسه") | 🔴 `users.resetPassword` (لازم حتى لو عم يعدّل على نفسه) | `{"new_password":"NewPassword123!","new_password_confirmation":"NewPassword123!"}` | 🔴 new_password (مو `password`) | None | 204 | 🔴 اسم الحقل الفعلي `new_password` مش `password`. |
| POST | /api/v1/users/{userId}/deactivate | Tenant Admin | 🔴 `users.deactivate` | None | None | None | 204 | 🔴 مستحيل تعمل deactivate لحسابك انت نفسك (ممنوع بالكود بشكل دائم، مو بس صلاحية). |
| GET | /api/v1/exam-sessions/{sessionId}/certificate | 🔴 صاحب الجلسة (candidate) فقط — ولا حتى الأدمن/الموظفين | None (فحص ownership مباشر) | None | None | None | 200 OK + ملف | |
| GET | /api/v1/exams/{examId}/results/export | Tenant Admin أو evaluator | `grading.view`/`evaluate`/`publish` | None | None | None | 200 OK + CSV | 🔴🔴 **ثغرة أمنية حالياً بالكود: الكونترولر ما فيه `authorize()` إطلاقاً — أي مستخدم مسجّل دخول فيه يحمّل نتائج أي امتحان لو عرف الـ UUID. لازم تضيفوا `$this->authorize('viewResult', AssessmentResult::class)` بالكونترولر (شوفي التصليح يلي عطيتك ياه سابقاً).** |
| GET | /api/v1/roles | Tenant Admin | 🔴 `roles.viewAny` | None | None | per_page | 200 OK | |
| POST | /api/v1/roles | Tenant Admin | 🔴 `roles.create` | `{"role_name":"Custom Role","description":"Custom role","role_category":"operational"}` | role_name, role_category | description, is_custom (🔴 شيلي `role_metadata` — مو مقبول بالإنشاء) | 201 Created | |
| PATCH | /api/v1/roles/{roleId} | Tenant Admin | 🔴 `roles.update` | `{"role_name":"Updated Role"}` | None | role_name, description, role_category, role_metadata | 200 OK | 🔴 الأدوار النظامية (`is_system_role = true`) ما يمكن تعديلها عبر الـ API إطلاقاً، بغض النظر عن الصلاحية. |
| DELETE | /api/v1/roles/{roleId} | Tenant Admin | 🔴 `roles.delete` | None | None | None | 200/204 | 🔴 نفس قيد الأدوار النظامية أعلاه. |
| POST | /api/v1/roles/{roleId}/users/{userId} | Tenant Admin | 🔴 `roles.assign` | None | None | None | 200 OK | |
| DELETE | /api/v1/roles/{roleId}/users/{userId} | Tenant Admin | 🔴 `roles.assign` | None | None | None | 200/204 | |
| GET | /api/v1/eligibility-chains | Tenant Admin أو Technical Evaluator | `eligibility.view` أو `eligibility.manage` | None | None | None | 200 OK | |
| POST | /api/v1/eligibility-chains | Tenant Admin أو Technical Evaluator | `eligibility.manage` | 🔴 `{"exam_id":"<uuid>","chain_step_number":1,"condition_type":"prior_exam_passed"}` | 🔴 exam_id, chain_step_number, condition_type (مو `chain_name`!) | 🔴 prerequisite_exam_id, condition_data, logical_operator (`AND`\|`OR`), min_score_required, is_satisfied_override_available, chain_metadata (مو `description`/`rules`!) | 201 Created | 🔴🔴 كامل الـ body كان غلط بالنسخة القديمة — الحقول القديمة (`chain_name`, `description`, `rules`) مش موجودة إطلاقاً بالكود. |
| GET | /api/v1/eligibility-chains/{chainId} | Tenant Admin أو Technical Evaluator | `eligibility.view` أو `eligibility.manage` | None | None | None | 200 OK | |
| PATCH | /api/v1/eligibility-chains/{chainId} | Tenant Admin أو Technical Evaluator | `eligibility.manage` | 🔴 `{"chain_step_number":2}` | None | 🔴 chain_step_number, prerequisite_exam_id, condition_type, condition_data, logical_operator, min_score_required, is_satisfied_override_available, chain_metadata | 200 OK | نفس ملاحظة الـ POST أعلاه. |
| DELETE | /api/v1/eligibility-chains/{chainId} | Tenant Admin أو Technical Evaluator | `eligibility.manage` | None | None | None | 200/204 | |
| GET | /api/v1/security/policies | Tenant Admin | 🔴 `security_policies.view` | None | None | None | 200 OK | |
| PATCH | /api/v1/security/policies | Tenant Admin | 🔴 `security_policies.update` | 🔴 `{"mfa_enabled":false}` | None | 🔴 mfa_enabled, mfa_method, password_min_length, password_require_uppercase, password_require_lowercase, password_require_numbers, password_require_special_chars, password_expiry_days, password_history_count, session_timeout_minutes, session_absolute_timeout_hours, session_force_reauth_on_privilege_change, ip_whitelisting_enabled, enable_biometric_auth, enforce_tls_1_3_minimum, disable_weak_ciphers, allowed_ip_ranges (مصفوفة سترنغز) | 200 OK | 🔴🔴 كامل الـ body كان غلط — لا يوجد `mfa_required` ولا `password_policy` (كـ object)؛ كل إعدادات كلمة السر حقول منفصلة flat. |
| GET | /api/v1/identity/profile | Authenticated user | None | None | None | None | 200 OK | |
| PATCH | /api/v1/identity/profile | Authenticated user | None | `{"first_name":"Jane"}` | None | 🔴 first_name, last_name, external_employee_id فقط (شيلي user_type, department_id, user_attributes — مش مقبولين هون، هدول بس عند الأدمن عبر `/users/{id}`) | 200 OK | هاد المسار الصح إنه المستخدم يعدّل بياناته هو بنفسه، بدون أي صلاحية. |
| GET | /api/v1/identity/permissions | Authenticated user | None | None | None | None | 200 OK | |
| GET | /api/v1/identity/sessions | Authenticated user | None | None | None | None | 200 OK | |
| DELETE | /api/v1/identity/sessions/all | Authenticated user | None | None | None | None | 204 | |
| DELETE | /api/v1/identity/sessions/{id} | Authenticated user | None | None | None | None | 204 | |
| GET | /api/v1/categories/tree | Tenant Admin أو Technical Evaluator | 🔴 `categories.manage` (ما فيه permission منفصل للقراءة) | None | None | None | 200 OK | |
| POST | /api/v1/categories | Tenant Admin أو Technical Evaluator | `categories.manage` | `{"title":"Math","description":"Math category"}` | title | parent_id, description | 201 Created | |
| PATCH | /api/v1/categories/{id}/move | Tenant Admin أو Technical Evaluator | `categories.manage` | `{"parent_id":"<uuid>"}` | None | parent_id | 200 OK | |
| DELETE | /api/v1/categories/{id} | Tenant Admin أو Technical Evaluator | `categories.manage` | None | None | None | 200/204 | |
| POST | /api/v1/questions/bulk-import | Tenant Admin أو Technical Evaluator | `questions.manage` | 🔴 **مو JSON — رفع ملف `multipart/form-data`** | 🔴 `file` (CSV أو TXT، حد أقصى 5MB) | None | 201 Created أو ملخص استيراد | 🔴🔴 القديم كان كأنه JSON body فيه `questions` array — غلط تماماً، الإندبوينت فعلياً بياخد ملف مرفوع (`file`) بصيغة `multipart/form-data`. أعمدة الـ CSV المتوقعة (من تعليق بالراوت): `category_code, question_title, question_type, question_text, stem, bloom_level, difficulty_level, choices_json, answer_json`. |
| GET | /api/v1/questions | Tenant Admin أو Technical Evaluator | 🔴 `questions.manage` (ما فيه permission منفصل للقراءة) | None | None | search, category_id, type | 200 OK | |
| POST | /api/v1/questions | Tenant Admin أو Technical Evaluator | `questions.manage` | 🔴 `{"category_id":"<uuid>","title":"Basic Addition","type":"mcq","question_text":"What is 2+2?","bloom_level":1,"choices":[{"option_text":"3","is_correct":false},{"option_text":"4","is_correct":true}]}` | 🔴 category_id, title, type, question_text, **bloom_level** (كان ناقص!) + حقول شرطية حسب `type`: `choices` إذا `type=mcq`، `correct_answer` إذا `type=true_false`، `accepted_answers` إذا `type=short_answer` | stem, difficulty_level, choices.\*.option_sequence, match_mode (`exact`\|`case_insensitive`), evaluator_instructions, psychometrics.p_value, psychometrics.discrimination_index, psychometrics.usage_count | 201 Created | 🔴🔴 قيم `type` المسموحة فعلياً: `mcq`, `true_false`, `short_answer`, `essay` — **مو `multiple_choice`**. القديم كان رح يفشل بالفاليديشن. |
| GET | /api/v1/questions/{id} | Tenant Admin أو Technical Evaluator | `questions.manage` | None | None | None | 200 OK | |
| PUT/PATCH | /api/v1/questions/{id} | Tenant Admin أو Technical Evaluator | `questions.manage` | `{"question_text":"Updated question"}` | None | title, category_id, question_text, difficulty_level, bloom_level, stem, choices, correct_answer, accepted_answers, match_mode, evaluator_instructions, psychometrics.\* | 200 OK | |
| DELETE | /api/v1/questions/{id} | Tenant Admin أو Technical Evaluator | `questions.manage` | None | None | None | 200/204 | |
| GET | /api/v1/competencies/tree | Tenant Admin أو Technical Evaluator | 🔴 `competencies.manage` (ما فيه permission منفصل للقراءة) | None | None | None | 200 OK | |
| POST | /api/v1/competencies | Tenant Admin أو Technical Evaluator | `competencies.manage` | `{"name":"Research","description":"Research competency"}` | name | parent_id, description | 201 Created | |
| PATCH | /api/v1/competencies/{id}/move | Tenant Admin أو Technical Evaluator | `competencies.manage` | `{"parent_id":"<uuid>"}` | None | parent_id | 200 OK | |
| DELETE | /api/v1/competencies/{id} | Tenant Admin أو Technical Evaluator | `competencies.manage` | None | None | None | 200/204 | |
| GET | /api/v1/exams | Tenant Admin أو Technical Evaluator | 🔴 `exams.view` | None | None | filters | 200 OK | ⚠️ Technical Evaluator ماعندوش `exams.view` فعلياً بالـ seeder المزروع — تأكدوا هل هاي مقصودة. |
| POST | /api/v1/exams | Tenant Admin أو Technical Evaluator | `exams.manage` | 🔴 `{"exam_name":"Math Final","exam_code":"MATH-FINAL","exam_type":"certification","total_questions":20,"total_duration_minutes":60}` | exam_name, exam_code, **exam_type** (كان ناقص!), total_questions, total_duration_minutes | exam_description, assessment_mode (`online`\|`hybrid`\|`paper`), pass_mark_percentage, difficulty_tier_level, is_adaptive_exam, is_randomized, allow_review_after_submit, allow_flagging_for_review, timer_visible_to_candidate, show_correct_answers_after, security_protocols, exam_metadata | 201 Created | 🔴 `exam_type` مطلوب وقيمه: `certification`, `placement`, `training`, `evaluation`, `practice`. |
| GET | /api/v1/exams/{examId} | Tenant Admin أو Technical Evaluator | `exams.view` | None | None | None | 200 OK | نفس ملاحظة الـ `exams.view` أعلاه. |
| PATCH | /api/v1/exams/{examId} | Tenant Admin أو Technical Evaluator | `exams.manage` | `{"exam_name":"Updated Exam"}` | None | exam_name, exam_code, exam_description, exam_type, assessment_mode, total_questions, total_duration_minutes, pass_mark_percentage, difficulty_tier_level, is_adaptive_exam, is_randomized, allow_review_after_submit, allow_flagging_for_review, timer_visible_to_candidate, show_correct_answers_after, security_protocols, exam_metadata | 200 OK | |
| DELETE | /api/v1/exams/{examId} | Tenant Admin أو Technical Evaluator | `exams.manage` | None | None | None | 200/204 | |
| POST | /api/v1/exams/{examId}/publish | Tenant Admin أو Technical Evaluator | `exams.publish` | None | None | None | 200 OK | |
| POST | /api/v1/exams/{examId}/archive | Tenant Admin أو Technical Evaluator | `exams.manage` | None | None | None | 200 OK | |
| GET | /api/v1/cohorts | Tenant Admin | 🔴 `cohorts.view` | None | None | None | 200 OK | |
| POST | /api/v1/cohorts | Tenant Admin | 🔴 `cohorts.manage` | `{"cohort_name":"Batch A","cohort_code":"BATCH-A","cohort_type":"batch"}` | cohort_name, cohort_code, cohort_type | cohort_description, parent_cohort_id, cohort_attributes | 201 Created | `cohort_type` قيمه: `team`, `department`, `batch`, `class`, `cohort`, `group`. |
| GET | /api/v1/cohorts/{cohortId} | Tenant Admin | `cohorts.view` | None | None | None | 200 OK | |
| PATCH | /api/v1/cohorts/{cohortId} | Tenant Admin | `cohorts.manage` | `{"cohort_name":"Updated Batch"}` | None | cohort_name, cohort_code, cohort_type, cohort_description, cohort_attributes, is_active | 200 OK | |
| DELETE | /api/v1/cohorts/{cohortId} | Tenant Admin | `cohorts.manage` | None | None | None | 200/204 | |
| GET | /api/v1/cohorts/{cohortId}/members | Tenant Admin | `cohorts.view` | None | None | None | 200 OK | |
| POST | /api/v1/cohorts/{cohortId}/members | Tenant Admin | 🔴 `cohorts.members.manage` | `{"user_id":"<uuid>"}` | user_id | membership_role (`member`\|`manager`\|`coordinator`\|`observer`) | 201 Created | |
| DELETE | /api/v1/cohorts/{cohortId}/members/{userId} | Tenant Admin | `cohorts.members.manage` | None | None | None | 200/204 | |
| POST | /api/v1/exam-sessions | Candidate | `exam_sessions.start` | `{"exam_id":"<uuid>"}` | exam_id | None | 201 Created | |
| GET | /api/v1/exam-sessions/{sessionId} | صاحب الجلسة أو staff | `exam_sessions.view` (أو صاحب الجلسة) | None | None | None | 200 OK | |
| POST | /api/v1/exam-sessions/{sessionId}/responses | صاحب الجلسة | `exam_sessions.manage` (أو صاحب الجلسة) | `{"session_item_id":"<uuid>","response_type":"text","response_text":"My answer"}` | session_item_id, response_type | response_text, 🔴 response_data, selected_options, file_upload_url, time_spent_seconds, time_elapsed_from_start_seconds, is_flagged_for_review, expected_item_version_lock | 200 OK | |
| POST | /api/v1/exam-sessions/{sessionId}/suspend | صاحب الجلسة | `exam_sessions.manage` (أو صاحب الجلسة) | None | None | None | 200 OK | |
| POST | /api/v1/exam-sessions/{sessionId}/resume | صاحب الجلسة | `exam_sessions.manage` (أو صاحب الجلسة) | None | None | None | 200 OK | |
| POST | /api/v1/exam-sessions/{sessionId}/complete | صاحب الجلسة | `exam_sessions.manage` (أو صاحب الجلسة) | None | None | None | 200 OK | |
| POST | /api/v1/exam-sessions/{sessionId}/terminate | 🔴 Staff فقط (Tenant Admin أو Technical Evaluator — **مو Proctor** رغم الاسم) | `exam_sessions.manage` | None | None | None | 200 OK | ⚠️ Proctor role ماعندوش `exam_sessions.manage` بالـ seeder فعلياً، فمش قادر ينهي جلسة، رغم إنه التعليق بالكود بيقول "proctors intervening in emergencies". |
| GET | /api/v1/exam-sessions/{sessionId}/result | صاحب الجلسة (بس بعد النشر) أو evaluator/admin | `grading.view/evaluate/publish` (وصول كامل) — وإلا صاحب الجلسة لنتيجته المنشورة فقط | None | None | None | 200 OK | |
| POST | /api/v1/exam-sessions/{sessionId}/heartbeat | صاحب الجلسة | `exam_sessions.manage` (أو صاحب الجلسة) | `{"metadata":{"client":"browser"}}` | None | metadata | 200 OK | لا يزيد `version_lock`. |
| POST | /api/v1/exam-sessions/{sessionId}/proctor-events | 🔴 Proctor (حساب نظام/أداة مراقبة) | 🔴 `proctoring.ingest` (مش "None" — كل مستخدم مسجّل دخول عادي لا يكفي) | `{"event_type":"attention","event_timestamp":"2026-07-20T10:00:00Z"}` | event_type, event_timestamp | event_category, event_payload, severity_level (`info`\|`warning`\|`critical`), 🔴 detection_confidence_score, screenshot_url, video_segment_url, detection_parameters | 201 Created | |
| GET | /api/v1/exam-sessions/{sessionId}/proctor-events | Proctor أو Tenant Admin | `proctoring.view` | None | None | None | 200 OK | |
| GET | /api/v1/exam-sessions/{sessionId}/pending-evaluations | Technical Evaluator | `grading.evaluate` أو `grading.view` | None | None | None | 200 OK | |
| PATCH | /api/v1/answer-evaluations/{evaluationId}/score | Technical Evaluator | `grading.evaluate` | `{"score_awarded":88.5}` | score_awarded | rubric_id, 🔴 rubric_criteria_json, evaluator_comments, requires_secondary_review | 200 OK | |
| POST | /api/v1/exam-sessions/{sessionId}/result/publish | Technical Evaluator أو Tenant Admin | `grading.publish` | None | None | None | 200 OK | |
| GET | /api/v1/exam-sessions/{sessionId}/result/publication-status | Technical Evaluator أو Tenant Admin | `grading.view` أو `grading.publish` | None | None | None | 200 OK | |
| GET | /api/v1/penalty-rules | Tenant Admin أو Proctor | `penalties.view` | None | None | None | 200 OK | |
| POST | /api/v1/penalty-rules | Tenant Admin | `penalties.manage` | `{"penalty_name":"Cheating","penalty_type":"points","trigger_condition":"rule"}` | penalty_name, penalty_type, trigger_condition | trigger_parameters, penalty_points, penalty_percentage, 🔴 is_cumulative, is_active, penalty_metadata | 201 Created | |
| GET | /api/v1/penalty-rules/{ruleId} | Tenant Admin أو Proctor | `penalties.view` | None | None | None | 200 OK | |
| PATCH | /api/v1/penalty-rules/{ruleId} | Tenant Admin | `penalties.manage` | `{"penalty_name":"Updated penalty"}` | None | penalty_name, penalty_type, trigger_condition, trigger_parameters, penalty_points, 🔴 penalty_percentage, is_cumulative, is_active, penalty_metadata | 200 OK | |
| DELETE | /api/v1/penalty-rules/{ruleId} | Tenant Admin | `penalties.manage` | None | None | None | 200/204 | |
| POST | /api/v1/penalty-rules/{ruleId}/activate | Tenant Admin | `penalties.manage` | None | None | None | 200 OK | |
| POST | /api/v1/penalty-rules/{ruleId}/deactivate | Tenant Admin | `penalties.manage` | None | None | None | 200 OK | |
| GET | /api/v1/exam-sessions/{sessionId}/sanctions | Tenant Admin أو Proctor | `penalties.view` | None | None | None | 200 OK | |
| POST | /api/v1/sanctions/{sanctionId}/void | Tenant Admin | `penalties.manage` | `{"reason":"Incorrectly applied"}` | reason | None | 200 OK | |
| POST | /api/v1/workflows | Tenant Admin أو Technical Evaluator | `workflows.manage` | `{"resource_type":"exam_result","resource_id":"<uuid>","workflow_type":"approval"}` | resource_type, resource_id, workflow_type | None | 201 Created | |
| GET | /api/v1/workflows/{workflowId} | Tenant Admin أو Technical Evaluator | 🔴 `workflows.manage` أو `workflows.approve` (مافي permission اسمها `workflow.view`) | None | None | None | 200 OK | |
| POST | /api/v1/workflows/{workflowId}/approve | Tenant Admin | 🔴 `workflows.approve` | None | None | None | 200 OK | ⚠️ Technical Evaluator ماعندوش هاي الصلاحية فعلياً — بس Tenant Admin. |
| GET | /api/v1/analytics/dashboard | Tenant Admin | `analytics.view` | None | None | None | 200 OK | ⚠️ ماعندوش Proctor ولا Technical Evaluator وصول فعلياً بالـ seeder المزروع. |
| GET | /api/v1/exams/{examId}/enrollments | Tenant Admin أو Technical Evaluator | 🔴 `exam_sessions.manage` (مو `enrollment.view` منفصلة) | None | None | None | 200 OK | |
| POST | /api/v1/exams/{examId}/enrollments | Tenant Admin أو Technical Evaluator | 🔴 `exam_sessions.manage` | `{"candidate_user_id":"<uuid>","cohort_id":"<uuid>"}` | candidate_user_id | cohort_id, start_window_date, end_window_date, max_attempts_allowed, enrollment_notes | 201 Created | |
| DELETE | /api/v1/exams/{examId}/enrollments/{enrollmentId} | Tenant Admin أو Technical Evaluator | 🔴 `exam_sessions.manage` | None | None | None | 200/204 | |

---

## ملخص سريع للأمور يلي لازم تتصرفي فيها قبل ما تبعتي الملف

1. **صلّحي `ReportController.php`** (التصليح موجود بردّي السابق) — ثغرة أمنية حقيقية.
2. **راجعي مع الباك اند**: هل `Technical Evaluator` المفروض يكون عنده `exams.view`؟ وهل `Proctor` المفروض يكون عنده `exam_sessions.manage`؟ هدول احتمال يكونوا bugs بالـ seeder مو قرارات مقصودة.
3. **وضحي مين بالضبط بيبعت `proctor-events`** — حساب المرشّح ولا حساب نظام مستقل.
4. بعد هيك الملف جاهز للفرونت اند.