# Exam Flow — End to End

All routes below are `/api/v1/...` against a tenant subdomain, `Authorization: Bearer <token>`
unless noted otherwise. Response envelopes and error codes follow
[ERRORS_AND_VALIDATION.md](ERRORS_AND_VALIDATION.md).

## 1. Build the exam (admin / evaluator, `exams.manage`)

**Create exam**
```
POST /exams
{
  "exam_name": "Math Basics",
  "exam_code": "MATH-101",          // unique per tenant
  "exam_type": "evaluation",        // certification|placement|training|evaluation|practice
  "total_questions": 1,
  "total_duration_minutes": 30,
  "pass_mark_percentage": 60.0,     // optional, default 60.0
  "is_adaptive_exam": false         // optional — see "Adaptive CAT" below
}
-> 201 { "data": { ...ExamResource... } }
```

**Create a section**
```
POST /exams/{examId}/sections
{ "section_name": "Main Section", "section_sequence": 1, "questions_in_section": 1 }
-> 201 { "data": { ...raw ExamSection row... } }
```

**Create a blueprint** (ties a section to a competency and sets selection constraints)
```
POST /exams/{examId}/blueprints
{
  "section_id": "...", "competency_id": "...",
  "min_questions_count": 1, "max_questions_count": 1,
  "min_weight_percentage": 100, "max_weight_percentage": 100
}
-> 201 { "data": { ...raw ExamBlueprint row... } }
```
The sum of `min_weight_percentage` across all blueprints in the same section must equal exactly
100 — the server enforces this and rejects an over/under-100 total.

**Approve the question version** (a question can't be used in a published exam until its current
version is approved)
```
POST /question-versions/{versionId}/approve   (no body)
-> 200 { "data": { ...QuestionVersion... } }
```

**Publish the exam**
```
POST /exams/{examId}/publish   (no body)
-> 200 { "data": { ...ExamResource, exam_status: "published" ... } }
```

## 2. Enroll a candidate (admin/proctor, `enrollments.manage`)

```
POST /exams/{examId}/enrollments
{
  "candidate_user_id": "...",
  "start_window_date": "2020-01-01T00:00:00",
  "end_window_date": "2030-01-01T00:00:00",
  "max_attempts_allowed": 5
}
-> 201 { "data": { ...EnrollmentResource... } }
```
`start_window_date`/`end_window_date` define the eligibility window — the session-start check
requires "now" to fall strictly inside this window at the moment the candidate calls
`POST /exam-sessions`, so keep the window comfortably wide for anything other than
"this exam should only be takeable during a specific slot" testing. A narrow or already-past
window is the most common cause of `eligibility_violation`.

## 3. Take the exam (candidate)

**Start a session**
```
POST /exam-sessions
{ "exam_id": "..." }
-> 201 { "data": { "session_id": "...", "current": { "session_item_id": "...", ... }, ... } }
```
`candidate_id` is never taken from the body — it's always the authenticated caller. Calling this
again for an already-in-progress session returns that same session rather than starting a new
one (safe to retry, no `Idempotency-Key` needed or honored here).

Common failures: `eligibility_violation` (422 — enrollment window/attempts issue),
`enrollment_not_found` (404 — candidate was never enrolled in this exam).

**Submit a response**
```
POST /exam-sessions/{sessionId}/responses
{
  "session_item_id": "...",
  "response_type": "mcq",
  "selected_options": ["<option-uuid>"],   // MCQ/true-false: the option's UUID, not its text
  "time_spent_seconds": 15,
  "time_elapsed_from_start_seconds": 15
}
-> 200 { "data": { ...ExamSessionResource, "current": {...next item or null...} ... } }
```
`time_spent_seconds` / `time_elapsed_from_start_seconds` must always be a real number — never
omit them or send `null`, the underlying columns are `NOT NULL`.

**Complete the session**
```
POST /exam-sessions/{sessionId}/complete   (no body)
-> 200 { "data": { ...ExamSessionResource, state: "completed" ... } }
```

## 4. Results and publication

Full details in [RESULTS_AND_CERTIFICATES.md](RESULTS_AND_CERTIFICATES.md) — short version:

```
GET  /exam-sessions/{sessionId}/result                       -> full result (candidates see it only once published)
GET  /exam-sessions/{sessionId}/result/publication-status     -> { result_status, publication_status, published_at, ... }
POST /exam-sessions/{sessionId}/result/publish   (no body)     -> publishes (safe to retry; no-op if already published)
```

For a **fully auto-gradable exam (all MCQ/true-false)**, the result finalizes and
**auto-publishes the instant grading finishes** — no explicit `publish` call is needed in that
case. `POST .../result/publish` is realistically only needed when a result has a manual-grading
component or is blocked on a pending penalty/approval workflow.

## 5. Certificates

```
GET /exam-sessions/{sessionId}/certificate   -> binary PDF download (candidate's own token; not JSON)
GET /certificates/verify/{token}             -> public, no auth: {"data":{"valid":true,...}} or 404 {"data":{"valid":false}}
```
A certificate is only generated automatically on publish if the grade is passing
(`is_passing_grade`).

## Adaptive CAT (Computerized Adaptive Testing)

Set `is_adaptive_exam: true` when creating the exam. Item selection then runs as **per-section,
sequential CAT**: each section adapts item difficulty to the candidate's running ability
estimate and stops the section once either the section's `max_items` (from its blueprint) is
reached or the ability estimate's standard error drops below a fixed threshold. Section 2 only
begins once section 1's stop condition fires, and a question version is never repeated across
sections in the same session.

**Constraints to design exams around (these are hard failures, not soft degrades):**
1. Adaptive item pools must be restricted to **auto-gradable types only** (`mcq`, `true_false`).
   An essay/short-answer question in an adaptive section's pool throws at session start.
2. Every candidate item must have **calibrated psychometrics**
   (`PATCH /question-versions/{id}/psychometrics` with a real `difficulty_index`/
   `discrimination_index`) — an uncalibrated pool throws.
3. **Every adaptive section needs at least one blueprint row.** A section with none throws at
   session start.
4. **Cross-section pool exhaustion is a hard failure, not a graceful fallback.** If section 2's
   competency pool overlaps too heavily with section 1's (all eligible items already used),
   starting section 2 throws. There is no automatic overlap check at blueprint-authoring time —
   avoid overlapping competency pools across adaptive sections, or size each pool generously.

There is no dedicated "known limitations" doc beyond what's captured here — these constraints
are enforced as runtime `RuntimeException`s with descriptive messages, not flagged ahead of time
by the blueprint/section creation endpoints.
