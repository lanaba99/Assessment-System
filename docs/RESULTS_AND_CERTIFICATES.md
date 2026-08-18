# Results and Certificates

## The two independent status fields

An `AssessmentResult` carries two separate status strings — do not conflate them:

| Field | Values | Means |
|---|---|---|
| `result_status` | `provisional` \| `final` | Is grading *done*? `final` once every answer evaluation for the session has left `pending_review` (all auto-graded, or all manually scored by an evaluator). |
| `publication_status` | `unpublished` \| `published` | Is the result *visible to the candidate*? |

## The behavior that surprises people: auto-publish

When a result becomes `final`, the backend **immediately sets `publication_status: published`
in the same step** — there is no separate human action required for a fully auto-gradable exam.
This is intentional (from the repository's own code comment): *"A completed automatic grade is
immediately candidate-visible. Keep genuinely incomplete/manual-review results unpublished until
they become final or are explicitly published by staff."*

Practical consequence for the frontend:

- **All-MCQ/true-false exam:** the candidate sees `result_status: "final"` and
  `publication_status: "published"` **immediately after `POST .../complete`** — poll
  `GET .../result/publication-status` right after completing, no separate publish step to wait
  for or trigger.
- **Exam with a manual-grading component (essay, short-answer, etc.):** the result stays
  `provisional`/`unpublished` until an evaluator scores every pending item
  (`PATCH /answer-evaluations/{id}/score`). Once the last pending item is scored, the same
  auto-publish logic applies — *unless* a pending penalty or an unapproved publication workflow
  blocks it, in which case `result_status` becomes `final` but `publication_status` stays
  `unpublished` until `POST /exam-sessions/{sessionId}/result/publish` is called once those
  blockers clear.

## Endpoints

```
GET  /exam-sessions/{sessionId}/result
```
- Evaluators/admins (`grading.view`/`grading.evaluate`/`grading.publish`) can read any session's
  result at any time.
- Candidates can only read their **own** result, and only once it's published.
- `404 result_not_ready` if no result row exists yet for the session (i.e. grading hasn't
  produced one — check the session actually reached `complete` first).

```json
{
  "data": {
    "result_id": "...", "session_id": "...", "candidate_id": "...", "exam_id": "...",
    "status": { "result_status": "final", "publication_status": "published" },
    "summary": {
      "raw_score": 1, "max_score": 1, "percentage": 100, "grade_letter": "A",
      "is_passing": true, "is_final": true,
      "totals": { "evaluations": 1, "pending_evaluations": 0, "correct": 1, "incorrect": 0 },
      "breakdown": [ { "question_id": "...", "score_awarded": 1, "is_correct": true, "...": "..." } ]
    },
    "timestamps": { "calculated_at": "...", "published_at": "..." },
    "metadata": { }
  }
}
```

```
GET /exam-sessions/{sessionId}/result/publication-status
```
A flatter, cheaper-to-poll shape — no `summary`/`breakdown`:
```json
{ "data": { "session_id": "...", "result_id": "...", "result_status": "final", "publication_status": "published", "published_at": "...", "result_calculated_at": "..." } }
```

```
POST /exam-sessions/{sessionId}/result/publish   (no body)
```
Guard order (all inside one DB transaction, row-locked):
1. Result must exist and be `result_status: final` → else `422 result_not_finalized`.
2. No pending penalty processing → else `422 penalty_processing_pending`.
3. Any required approval workflow must already be approved → else `422 workflow_not_approved`.
4. Safe to call on an already-published result — no-op, no error. This is why it's safe to
   retry without an `Idempotency-Key`.

On success, if `is_passing_grade` is true, a certificate PDF is generated and stored
automatically as part of the same call — no separate "generate certificate" step exists.

## Certificates

```
GET /exam-sessions/{sessionId}/certificate
```
Binary PDF download (`Storage::disk('local')->download(...)`) — **not** the JSON envelope.
Requires the certificate's owner's own token (candidate downloading their own certificate).
`404` if no certificate exists for the session (e.g. the result never became a passing,
published grade).

```
GET /certificates/verify/{token}
```
Public, no auth — for use by anyone verifying a certificate's authenticity (e.g. an employer),
not just the tenant's own frontend. Route/parameter is literally named `{token}` (maps to the
certificate's `certificate_code`).

**Response shape deviates from the standard error envelope:**
```json
200 { "data": { "valid": true, "certificate_code": "...", "issued_at": "..." } }
404 { "data": { "valid": false } }
```
The not-found case is `data.valid = false` with a `404` status, not `{"error": {...}}` — handle
this endpoint's failure case explicitly rather than assuming the standard error shape applies
everywhere.
