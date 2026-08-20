# EAE Thesis Diagram Package

This directory contains the thesis-quality architecture and analysis diagram package for the
EAE Assessment Platform, generated against **commit `5e0d16b`** (Stage 1–3 complete, CI-validated,
pushed to `origin/main`).

- **Primary deliverable:** [`EAE_Thesis_Diagrams.drawio`](./EAE_Thesis_Diagrams.drawio) — a single
  editable, multi-page diagrams.net (draw.io) file, 38 pages.
- **This file** explains the page index, notation conventions, source-of-truth rules, and what is
  implemented vs. deferred in the underlying system.

Every diagram was built strictly from repository evidence (`app/`, `routes/`, `config/`,
`database/migrations/`, `docs/`, CI workflows, compose/Docker files) — no invented classes, tables,
endpoints, roles, states, or infrastructure. Six parallel research passes were run against the
codebase before any drawing began; every page carries an **Evidence:** footer citing the exact
files consulted.

## How to open

Open `EAE_Thesis_Diagrams.drawio` in [diagrams.net](https://app.diagrams.net/) (desktop app,
browser, or VS Code extension). Each of the 38 tabs at the bottom of the window is a separate page.
Do not flatten this into a single canvas — the multi-page structure is intentional and required.

## Page index

| # | Page | Kind | Status |
|---|---|---|---|
| 1 | Cover and Diagram Index | Master | — |
| 2 | Use Case Diagram | Master | Verified |
| 3 | High-Level System Architecture (C4) | Master | Verified |
| 4 | Multi-Tenancy Data Isolation | Detail | Verified |
| 5 | Authentication and Authorization Flow | Detail | Verified |
| 6 | Domain / Module Dependency Diagram | Master | Verified |
| 7 | Laravel DDD Package / Layer Diagram | Detail | Verified |
| 8 | Master ERD | Master | Verified |
| 9–22 | Per-Module ERDs (14 domains, one page each) | Detail | Verified / Not Applicable (Shared) |
| 23 | Domain Model to Database Mapping | Detail | Verified |
| 24 | Role and Permission Matrix | Master | Verified |
| 25 | Exam Session State Machine | Detail | Verified |
| 26 | Approval Workflow State Machine | Detail | Verified |
| 27 | Enrollment Lifecycle — No Independent FSM Warranted | Detail | Not Applicable |
| 28 | Exam Session Sequence | Detail | Verified |
| 29 | Approval Workflow Sequence | Detail | Verified |
| 30 | Candidate Exam-Taking Activity Diagram | Detail | Verified |
| 31 | Grading / Evaluation Activity Diagram | Detail | Verified |
| 32 | Class Diagram — Exam / Exam Session Core | Detail | Verified |
| 33 | Class Diagram — Grading / Result / Certificate Core | Detail | Verified |
| 34 | Class Diagram — Workflow / Approval Core | Detail | Verified |
| 35 | Component Diagram | Master | Verified |
| 36 | Deployment Diagram | Master | Verified |
| 37 | Risks and Limitations | Master | Verified |
| 38 | Project Timeline (Phases) | Master | Verified (phase labels only, no dates) |

Pages 9–22 (module ERDs), in order: Analytics, Central, Cohorts, Competency, ExamEngine,
ExamSession, Grading, Identity, Penalties, Proctoring, QuestionBank, Rules, Shared, Workflows —
the exact 14 folders under `app/Domains/`, in that order, which also matches
`domain_analysis_output/analysis.json`'s independently-generated domain list (cross-checked, exact
match).

**Master vs. Detail:** "Master" pages give a whole-system view (use cases, architecture, domain
map, master ERD, role matrix, component/deployment, risks, timeline). "Detail" pages zoom into one
mechanism (a single module's ERD, one state machine, one sequence, one class cluster).

## Notation

- **UML 2.x** — class diagrams (32–34), state machines (25–26), use-case diagram (2).
- **C4-style container/deployment notation** — pages 3 and 36, with explicit trust/environment
  boundaries (dashed containers).
- **Standard ERD notation** — pages 8–22: swimlane entity boxes, `PK`/`FK`/`UK` markers, connector
  lines for foreign keys. Not every column is shown — only PKs, FKs, and columns material to the
  page's narrative, per the task brief.
- **Sequence diagrams** — pages 5, 28, 29: lifeline actors with solid synchronous-call arrows and
  dashed return arrows.
- **Activity diagrams** — pages 30, 31: filled-circle start, ringed-circle stop, rhombus decisions.
- **Component diagram** — page 35: `«component»`-stereotyped boxes with dependency and datastore
  edges.

Every page also carries:
1. A numbered figure title.
2. A one-line purpose statement.
3. A compact legend.
4. An **Evidence:** source note citing the files consulted.
5. A status badge — `Implemented`, `Verified`, `Manual Production Action`, `Deferred`, or
   `Not Applicable`.
6. A footer: *"EAE — Thesis Architecture Package — Source: verified repository."*

### Status labels, defined

- **Verified** — the diagram content was directly confirmed by reading code/config/migrations.
- **Implemented** — the feature is live and running (used within diagrams for individual
  components, e.g. security response headers).
- **Manual Production Action** — the item is a real, documented pre-deploy step that a human/
  operator must perform (TLS termination, secrets injection, managed DB/Redis) — not something the
  application code does itself.
- **Deferred** — explicitly future work; scaffolding may exist (tables/models) but there is zero
  dispatch/controller/route code. Applies to Webhooks throughout this package.
- **Not Applicable** — the page intentionally contains no diagram of the requested kind because the
  evidence does not support one (Shared domain ERD, Enrollment state machine).

## Source-of-truth rule

Every claim in every diagram is grounded in the current repository, not in planning documents. Two
pre-existing planning artifacts were explicitly **not** trusted as schema/architecture ground truth,
and the discrepancies are recorded here per the task's instructions:

- **`erDiagram.txt`** (repo root) is the oldest artifact examined (dated ~May 11, predates
  `domain_analysis_output/` by ~7 weeks and `docs/` by ~3 months). It contains 91 tables, including
  `WEBHOOK_EVENTS`/`WEBHOOK_LOGS` (confirmed not implemented — scaffolding only) and
  `BACKUP_JOBS`/`BACKUP_SCHEDULES` (confirmed dead scaffolding per `docs/BACKUP_AND_RESTORE.md`). It
  also names a `tenants.tenant_id` primary key that does not match the real migration
  (`tenants.id`), and includes a `subdomain` column on `tenants` that was later dropped
  (`2026_05_23_000010_drop_subdomain_from_tenants_table.php`). All ERD pages in this package were
  built directly from `database/migrations/**`, not from `erDiagram.txt`.
- **`domain_analysis_output/analysis.json`**, by contrast, **is** trustworthy: its 14-domain list
  is an exact match (membership and order) against the live `app/Domains/` folder structure, and its
  circular-dependency and event-map data corroborate this package's independent code analysis (page
  6). It was used as corroborating evidence, not as the sole source.
- Two minor **doc-internal** inconsistencies were noted but do not affect any diagram: (a)
  `docs/TENANCY.md` states there is no `X-Tenant-ID` header, while `docs/SECURITY_BASELINE.md`'s
  CORS section lists `X-Tenant-ID` in `allowed_headers` (the header is allow-listed but unused —
  not a contradiction in behavior); (b) the repository uses two independent, unmapped timeline
  labeling schemes ("Phase N" in `API-CONTRACT.md` vs. "Stage N" in `docs/`) — see page 38.

Primary source files consulted (non-exhaustive): `app/Domains/**` (all 14 domains — Models,
Services, Contracts, Repositories, Policies, Providers), `app/Http/{Controllers,Middleware}/**`,
`routes/{api,tenant,web,console}.php`, `bootstrap/app.php`, `config/{tenancy,auth,sanctum,database,
queue,mail,filesystems}.php`, `database/migrations/{landlord,tenant}/**` (105 files),
`database/seeders/{TenantMasterSeeder,IdentityPermissionsSeeder}.php`, `compose.yaml`,
`compose.prod.yaml`, `docker/production/**`, `.github/workflows/tests.yml`, and every file under
`docs/` (`AUTHENTICATION.md`, `TENANCY.md`, `EXAM_FLOW.md`, `RESULTS_AND_CERTIFICATES.md`,
`SECURITY_BASELINE.md`, `PRODUCTION_OPERATIONS.md`, `DEPLOYMENT_CHECKLIST.md`,
`BACKUP_AND_RESTORE.md`, `API_HANDOFF.md`, `ERRORS_AND_VALIDATION.md`, `ENVIRONMENT_SETUP.md`).

## Implemented vs. deferred — the headline items

| Item | Status | Where it shows up |
|---|---|---|
| Webhooks | **Deferred** — `webhook_events`/`webhook_logs` tables and `WebhookConfig`/`WebhookDeliveryLog` models exist; zero controller, route, or dispatch code anywhere | Pages 1, 3, 10, 22, 29, 35, 37 (always marked deferred, never implemented) |
| CAT / adaptive testing | **Implemented** — IRT-based `AbilityEstimationService`, `AdaptiveCATStrategy` | Pages 19, 30, 35 |
| Manual grading review | **Implemented** — `ManualEvaluationServiceImpl`, 8 manual-review question types | Pages 15, 31, 33 |
| Email notifications | **Implemented** — 2 real Notification classes (`PasswordResetRequested`, `UserInvited`), mail channel only | Pages 3, 35 |
| Backups | **Not automated** — manual commands documented only | Pages 3, 37 |
| TLS/HTTPS termination | **Manual Production Action** — not in this repo, external LB/CDN required | Pages 3, 36, 37 |
| TrustHosts middleware | **Deferred** — deliberately not enabled (dynamic tenant subdomains) | Page 37 |
| WAF / reverse-proxy rate-limit backstop | **Deferred** — app-layer limiting only | Pages 3, 37 |
| Monitoring/APM | **Deferred** — hooks documented, no vendor chosen | Pages 3, 37 |
| Production compose stack | **Config-validated only** — never built or run | Pages 3, 36, 37 |

## Items marked unverified / deferred inside individual pages

- `Analytics.GeneratedReport/ReportTemplate/ScheduledReport` and several `Central` log/backup
  models have no verified writer service within their own domain (page 9, 10) — flagged, not
  invented.
- `Workflows` has 6 models (`AssessmentChecklistItem`, `ManualAssessment`, `EvaluatorObservation`,
  `GroupDashboard`, `BridgeEntryHybrid`, `ChecklistResponse`) with real schemas but no confirmed
  service/controller owner (page 22, 34).
- `bulk_import_templates`, `result_publication_workflow`, `behavioral_analytics` are orphan tables
  (real schema, no Eloquent model) — marked on pages 15, 18, 19.
- `workflows.approve` is granted to **no seeded tenant role** — a real gap in the seed data, not a
  design flaw (pages 2, 24, 26, 37).
- `exam_enrollments.attempts_used` is never incremented by any code path — the "attempts exhausted"
  eligibility gate is currently unreachable in practice (pages 14, 27, 37).
- Tenant-not-found exceptions have no explicit handler and fall through to the generic 500 response
  (page 37).
- 6 circular domain dependencies exist at the Eloquent-relationship level (page 6) — documented as a
  known architectural characteristic, not silently omitted.

## Diagrams intentionally omitted or simplified

- **No per-endpoint use-case diagram.** Page 2 groups ~87 tenant routes + 7 central routes into 10
  bounded-area categories per the task brief ("do not draw every endpoint as a use case").
- **No Enrollment state machine (page 27).** `exam_enrollments.enrollment_status` is a two-value
  flag (`active`/`revoked`) written by two independent, unguarded setter methods — it does not meet
  the bar for a UML state machine. This is explained on page 27 rather than fabricated.
- **No Shared-domain ERD (page 21).** `app/Domains/Shared` owns zero tables (Traits only) —
  explained rather than fabricated.
- **No calendar-dated Gantt chart (page 38).** No calendar dates exist anywhere in the repository
  for any milestone. Page 38 shows ordinal project phases as documented in-repo
  (`API-CONTRACT.md`'s "Phase N" labels and `docs/`'s "Stage N" labels), explicitly not to scale and
  explicitly not a calendar claim.
- **Master ERD (page 8) shows 28 of 89 tenant tables + 9 landlord tables** — a representative
  subset by design; full column-level detail for every table is on the 14 per-module ERD pages.
- **Component/domain-dependency diagrams show a curated edge subset** where the full graph (~44
  edges on page 6) would be too dense to read at component-capability granularity (page 35).

## Validation performed

- XML well-formedness confirmed via `xmllint --noout` and Python's `xml.etree`/`minidom` parsers.
- All 38 `<diagram>` pages present, uniquely named, in order.
- No page contains zero shapes.
- Every edge's `source`/`target` resolves to a real vertex on the same page (no dangling
  connectors) — verified programmatically across all 38 pages.
- Every cell's `parent` resolves to a real container on the same page (swimlane/table children
  checked, not just top-level shapes).
- Grepped the full file for `webhook` — every occurrence is labeled deferred/scaffolding; none are
  drawn as an implemented flow, route, or integration.
- Grepped the full file for credential-shaped strings (`password`, `secret`, `api_key`,
  `Bearer ...`) — all hits are schema/field names or feature descriptions, no real credentials or
  tokens appear anywhere in the package.
