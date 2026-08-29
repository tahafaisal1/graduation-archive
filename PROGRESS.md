# Graduation Archive System — Project Progress & Documentation

---

## 1. Project Summary

### System Name and Purpose

**Graduation Archive System** (نظام أرشفة مشاريع التخرج) for the Electronic Technology College. The system archives and classifies graduation projects, supports a multi-role approval workflow, provides search/filter capabilities, and offers management reports.

### Tech Stack (Exact Versions)

| Layer | Technology | Version |
|---|---|---|
| Backend | Laravel (Framework) | ^12.0 |
| Language | PHP | ^8.2 |
| Frontend | Vue | ^3.5.13 |
| SPA Bridge | Inertia.js (Laravel adapter) | ^2.0 |
| Inertia.js (Vue adapter) | @inertiajs/vue3 | ^2.0.0-beta.3 |
| Styling | Tailwind CSS | ^3.4.1 |
| Language | TypeScript | ^5.2.2 |
| Database | MariaDB | 10.4 (MySQL compatible) |
| Auth | Laravel Breeze (built-in) | — |
| RBAC | spatie/laravel-permission | ^6.25 |
| Excel Export/Import | maatwebsite/excel | ^3.1 |
| PDF Generation | barryvdh/laravel-dompdf | ^3.1 |
| Build Tool | Vite (laravel-vite-plugin) | ^1.0 |
| Testing | Pest PHP | ^3.8 |
| UI Primitives | radix-vue | ^1.9.11 |
| Route helpers | ziggy-js | ^2.4.2 |

### Major Changes

- **2026-08-29 — Phase 1 Closeout (5 fixes).** (1) `Welcome.vue` — every login CTA removed
  (`/login` still reachable by URL). (2) `AppSidebar.vue` — "تصفح المشاريع" removed from all role
  menus. (3) **Expanded search** — `SearchService::proposalTextMatch()` / `projectTextMatch()`
  OR-match title/description/academic_year/student name/supervisor name/examiner name/department
  name/specialization name (`LOWER(col) LIKE`, term trimmed + `mb_strtolower`'d server-side);
  `PublicController::browse()` uses `projectTextMatch` (still archived-projects-only); new
  `SearchService::searchInternal()` powers `/search` by merging a pending-proposals query with an
  all-non-deleted-projects query into one manually-built `LengthAwarePaginator` of normalized rows
  ({type, entity_label "مقترح"/"مشروع قيد التنفيذ"/"مشروع مؤرشف", route, route_id, …});
  `Search/Index.vue` renders the flat badged list. `detectSimilarity()` stays title-only.
  (4) **`users.registration_number` → `users.employee_number`** — standalone reversible migration
  `2026_08_29_000100_...`; `users` is staff-only, distinct from the untouched
  `proposal_students.registration_number`. `UserFactory` now emits `employee_number`.
  (5) **Invite-only account creation** — new `staff_invitations` table (sha256-hashed single-use
  token, see Section 2); `users.password` made nullable (`2026_08_29_000300_...`).
  `Admin\UserController::store()` creates the user with `password` NULL + `is_active` false and
  emails a `URL::temporarySignedRoute` link (`/setup-password/{token}?email=…`, 24h) via
  `StaffInvitationMail` (Arabic RTL Blade). `SetupPasswordController` (guest middleware) verifies
  signature + hashed token + email match + not-expired + not-used + not-already-activated — any
  failure renders `auth/InvitationInvalid` (never a 500); a valid POST hashes the password, sets
  `is_active`/`email_verified_at`, marks the token used, logs in → `/dashboard`. POST rate-limited
  5/hour/IP (`throttle:setup-password` limiter in `AppServiceProvider`); every attempt logged
  without the token. `LoginRequest::authenticate()` now also rejects `is_active=false`.
  `admin.users.resend-invitation` (super_admin) re-issues a fresh token for any non-activated
  account. `Admin/Users/Index.vue` create modal drops the password field; a "بانتظار التفعيل"
  badge + "إعادة إرسال الدعوة" action appear for accounts with a NULL password. The Breeze
  forgot-password flow was already present and is untouched.
- **2026-08-25 — Project Finalize/Archive.** A nullable `final_file_path` string column was added
  to `projects` (migration `2026_08_25_150000_add_final_file_path_to_projects_table.php`, placed
  `->after('final_score')`). `Project::canBeFinalizedBy(User $user): bool` (super_admin always;
  dept_manager/dept_staff of `proposal->department_id`; false once the project is already
  archived) and `Project::finalizationBlockers(): array` (returns Arabic-language blocker messages
  unless exactly 2 examiners are assigned and a final score is set) gate the new
  `POST /projects/{id}/finalize` route (`ProjectController::finalize()`), authorized and validated
  by `FinalizeProjectRequest` (PDF only, max 15MB, plus the blockers above). On success the
  uploaded file is stored to `projects/final` on the `public` disk, `final_file_path` is set, and
  `status_id` moves to `Project::STATUS_ARCHIVED`. Once archived,
  `ProjectExaminerController::assign()`/`remove()` and `EvaluationController::store()`/
  `updateScore()` all reject the request with a flash error (guard clause checking
  `status_id === Project::STATUS_ARCHIVED`) — previously these had no such lock. On the frontend,
  `Projects/Show.vue` gained a finalize card (an orange `ConfirmDelete` confirm-dialog variant)
  shown to dept_manager/dept_staff/super_admin while the project isn't archived yet, displaying
  any outstanding readiness blockers and, once archived, a download link for the final file;
  `Public/Show.vue`'s download link now points at `project.final_file_path` instead of
  `project.proposal.draft_file_path`, closing the previously-flagged gap where a freshly
  instantiated project had no path to becoming visible on the public site. See
  `.superpowers/sdd/2026-08-25-project-finalize-archive/` for the full per-task history.
- **2026-08-24 — Proposal/Project Split.** The previously-conflated `Project` entity is now two
  entities: **`Proposal`** (the paper-approved form — title/description/dept/spec/supervisor/
  students/PDF; 2-state مقترح↔مؤرشف lifecycle via `project_status`; never graded) and **`Project`**
  (the actual in-progress/graded work — `belongsTo` a `Proposal` via a unique, `restrictOnDelete`
  `proposal_id`; reads `supervisor`/`students` by reference through `$project->proposal`, never
  duplicating them). `Proposal::instantiateProject(User $actor): Project` is the single atomic
  action (`DB::transaction()`-wrapped) that flips a proposal مقترح→مؤرشف and creates its linked
  `Project` row in one step; gated by `Proposal::canBeInstantiatedBy()` (ported from the old flat
  `Project::canBeArchivedBy()`). Data migrated via `php artisan proposals:migrate-legacy-data`
  (`app/Console/Commands/MigrateProposalProjectData.php`), which first cleans up legacy project
  id=1 (found مقترح but with examiners/evaluations/a score already attached — now structurally
  impossible under the split) before copying 25 proposals / 20 projects forward. `visit_count` and
  `instantiated_by`/`instantiated_at` live on `projects` (view-count and instantiation-actor
  concepts belong to the instantiated work, not the form). New route names:
  `proposals.index/create/store/show/edit/update/destroy` + `proposals.instantiate`; `projects.*`
  is now GET-only (`index`/`show`) — no create/store/edit/update/destroy on `Project` directly.
  **Flagged limitation (closed 2026-08-25):** at the time of this change, no UI existed to move a
  project from قيد التنفيذ (in progress, the default `instantiateProject()` lands it at) to مؤرشف
  (archived) in `project_lifecycle_status` — public browse/show only ever surfaced مؤرشف projects,
  so a freshly-instantiated project was invisible there until its status was flipped some other
  way. This gap was closed by the Project Finalize/Archive change above. See Section 2 below for
  the full updated schema and `.superpowers/sdd/2026-08-24-proposal-project-split/` for the complete
  per-task history (schema, models, data migration, controllers/routes, frontend, full-suite
  realignment — 232/232 tests passing, 0 failures).

### Bugfixes / Corrections

- **2026-08-26 — Supervisor "مشاريعي" route, proposal/project terminology, instantiate-button
  wording.** Fixes 3 issues confirmed by a read-only gap analysis
  (`docs/analysis/current-system-behavior.md` on branch `analysis-current-system-behavior`, issues
  #2/#3/#4), landed on branch `fix/supervisor-projects-and-terminology` off `main` (deliberately not
  off the still-unmerged finalize-archive PR #1). Root causes and fixes:
  - The supervisor sidebar's "مشاريعي" link (`AppSidebar.vue`) pointed at `/projects/my`, a route
    that never existed — `GET /projects/{id}` (no numeric constraint) would have swallowed it as a
    literal `$id="my"` string, throwing a `TypeError`, and no supervisor-scoping query existed
    anywhere in `ProjectController`. Added `GET /projects/my` (name `projects.my`, registered
    *before* `/projects/{id}` for route-order correctness) with `role:supervisor` middleware (403
    for every other role), and `ProjectController::myProjects()` scoping to
    `Project::whereHas('proposal', fn ($q) => $q->where('supervisor_id', $request->user()->id))`.
    Reused `Projects/Index.vue` via a new optional `heading` prop instead of a new page.
  - `Dashboard.vue`, `Search/Index.vue`, and all 4 `Reports/*.vue` pages labeled `Proposal`-backed
    counts as "مشروع/مشاريع" (Project) instead of "مقترح/مقترحات" (Proposal) — confirmed root cause:
    `ReportService` sources nearly every count field from `Proposal`/`proposals()` relations; only
    `avg_score`/`scored_count` are genuinely `Project`-backed (via a join on `projects.final_score`)
    and were deliberately left worded as "مشاريع". ~24 Arabic-copy edits, no route/prop/key renamed.
    Also renamed `Search/Index.vue`'s `project` loop variable (iterating Proposal rows) to
    `proposal` for clarity — the id it passed to `proposals.show` was already correct.
  - `Proposals/Show.vue`'s instantiate-proposal button read "تنزيل المشروع" ("Download the
    project") for an action that archives the proposal and creates a `Project` row; its own confirm
    dialog already had a correct `title`/`message` but a `confirm-label` that still said "تنزيل
    المشروع", disagreeing with the dialog's own title. Both now consistently say "إنشاء المشروع".
    `Proposals/Index.vue` had the identical bug on its own row-action button and `ConfirmDelete`
    instance — missed by the gap analysis (scoped to `Show.vue` only) and by this branch's first
    pass; caught by `/code-review` on the full branch diff and fixed the same way.
  - `tests/Feature/Project/MyProjectsTest.php` — 3 new tests (6 assertions): supervisor sees only
    their own projects, all 4 non-supervisor roles (super_admin, dept_manager, dept_staff, viewer)
    get 403, the route renders `Projects/Index` with the `مشاريعي` heading and correct data. Full
    suite: 238/238 (0 failures), frontend build clean.
  - Issue #1 from the analysis (sidebar-mismatch, previously confirmed non-existent via live
    reproduction): the `/proposals` → Proposal Show → "عرض المشروع" → `/projects/{id}` chain is not
    touched by any file this branch modifies (confirmed by diff); re-verified live via Playwright
    as part of this branch's final review, see the branch's PR description for the walkthrough.

- **2026-08-24 — Ghost "في انتظار الموافقة" status badge removed.** Root cause:
  `resources/js/composables/useProjectStatus.ts` still labeled the "مقترح" status as
  "في انتظار الموافقة" (yellow), a leftover from before the system moved to paper-based approval.
  Confirmed via direct DB query that no stale data was involved — `project_status` has exactly 2
  rows (`مؤرشف`, `مقترح`) and all 25 seeded projects reference one of them. Backend authorization
  (`Project::canBeModifiedBy()`/`canBeArchivedBy()`, the three project FormRequests,
  `ProjectController::edit()`) and `Projects/Show.vue` were already fully correct for the 2-status/
  paper-approval model — only the frontend label/color map and one dashboard stat card title
  needed fixing. `isPendingApproval()` renamed to `isProposalStatus()` throughout
  `Projects/Index.vue` and `Projects/Show.vue`.

### Current Development Phase and Status

**Phase 1 Complete** — All planned Phase 1 features have been implemented and tested. The full test suite passes at 208/218 (10 pre-existing failures due to ext-zip disabled in XAMPP environment).

### Total Features: Implemented vs Planned

| Phase | Feature | Status |
|---|---|---|
| A | Models + Migrations + Seeders | Complete |
| B | RBAC Middleware + Role-based Access | Complete |
| C | Department & Specialization Management | Complete |
| D | Project CRUD with PDF upload | Complete |
| E | Search + Filter + Similarity Detection | Complete |
| F | Examiners + Evaluations + Final Score | Complete |
| G | Bulk Import (Excel + ZIP PDFs) | Complete |
| H | Reports + Dashboard | Complete |
| I | User Management (Admin) | Complete |
| — | Public Browse (no auth) | Complete |
| — | UI/UX (Arabic RTL, design system) | Complete |
| Phase 2 | Student eligibility system | Not started |
| Phase 2 | Full 11-stage project lifecycle | Not started |
| Phase 2 | Supervisor approval gates | Not started |
| Phase 2 | Defense scheduling | Not started |
| Phase 2 | Document milestone tracking | Not started |
| Phase 2 | Status history logging | Not started |

---

## 2. Database Schema

### Table: users

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| name | varchar(255) | No | — | Full name |
| email | varchar(255), unique | No | — | Login email |
| email_verified_at | timestamp | Yes | null | Email verification timestamp |
| password | varchar(255) | Yes | null | Hashed password. Nullable since 2026-08-29 (`2026_08_29_000300_make_users_password_nullable`) — invited staff have NULL until they complete the setup-password flow |
| remember_token | varchar(100) | Yes | null | Remember-me token |
| employee_number | varchar(255), unique | Yes | null | Staff/employee number (college staff only; renamed from `registration_number` on 2026-08-29). `users` is a STAFF-ONLY table — students never log in; the genuine student number lives on `proposal_students.registration_number` and is unrelated |
| department_id | bigint unsigned, FK | Yes | null | FK to departments.id (nullOnDelete) |
| is_active | boolean | No | true | Account active flag. Enforced at login since 2026-08-29 (`LoginRequest` rejects `is_active=false`); invited staff are created `false` and flipped `true` on setup completion |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** `department_id` references departments(id), nullOnDelete.
**Spatie tables:** `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `roles`, `permissions` are created by the Spatie migration.

### Table: staff_invitations (added 2026-08-29)

Backs the invite-only staff account-creation flow. One outstanding row per not-yet-activated user
(`StaffInvitation::issueFor()` deletes any prior row before inserting a fresh one).

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| user_id | bigint unsigned, FK | No | — | FK to users.id, cascadeOnDelete |
| token_hash | varchar(64), index | No | — | `hash('sha256', $plainToken)` — the plaintext token lives only in the emailed signed URL |
| expires_at | timestamp | No | — | `now()->addHours(24)` at issue |
| used_at | timestamp | Yes | null | Set when the setup-password POST succeeds; a used row rejects further attempts |
| created_at | timestamp | Yes | null | — (no `updated_at`) |

**Foreign keys:** `user_id` references users(id), cascadeOnDelete.

### Table: password_reset_tokens

| Column | Type | Nullable | Default |
|---|---|---|---|
| email | varchar (primary key) | No | — |
| token | varchar | No | — |
| created_at | timestamp | Yes | null |

### Table: sessions

| Column | Type | Nullable |
|---|---|---|
| id | varchar (primary key) | No |
| user_id | bigint unsigned, index | Yes |
| ip_address | varchar(45) | Yes |
| user_agent | text | Yes |
| payload | longtext | No |
| last_activity | integer, index | No |

### Table: departments

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| name | varchar(255), unique | No | — | Department full name |
| code | varchar(10), unique | No | — | Short code (e.g., SW, NET, ELEC) |
| description | text | Yes | null | Optional description (Arabic text) |
| is_active | boolean | No | true | Active flag |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

Note: `description` column added by migration `2026_06_17_000001_add_description_to_departments_table`.

### Table: specializations

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| department_id | bigint unsigned, FK | No | — | FK to departments.id (cascadeOnDelete) |
| name | varchar(255) | No | — | Specialization name |
| is_active | boolean | No | true | Active flag |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** `department_id` references departments(id), cascadeOnDelete.

### Table: project_status

Backs `proposals.status_id` (the Proposal lifecycle) since the 2026-08-24 split. Do not confuse
with `project_lifecycle_status` below, which backs `projects.status_id` (the Project lifecycle) —
these are two independent 2-row reference tables with different meanings at the same ids.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | tinyint unsigned, PK | No | auto | Primary key (tiny int) |
| status_name | varchar(255), unique | No | — | Status identifier string |
| sort_order | tinyint unsigned | No | 0 | Display order |
| is_active | boolean | No | true | Active flag |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Seeded statuses (from ProjectStatusSeeder, updated 2026-08-17 — lifecycle scoped down to 2 statuses):**

| id | status_name | sort_order | is_active |
|---|---|---|---|
| 1 | مؤرشف (archived) | 2 | true |
| 2 | مقترح (proposal) | 1 | true |

Note: IDs kept as originally seeded (id=1 already meant "archived" everywhere in the codebase),
only the Arabic text and sort_order changed. The original 8 Phase-2 placeholder statuses
(supervisor_approved, hod_approved, in_progress, ready_for_defense, under_defense,
revisions_required, rejected, cancelled) are removed — they were never referenced by any
controller, service, or Vue component (confirmed by codebase-wide audit), only present in
seed data. Supervisor and department approval happen on paper, outside the system (updated
2026-08-23) — there is no digital approval tracking; see the `projects` table note below for
what replaced the short-lived `supervisor_approved_by/_at`/`department_approved_by/_at` fields.

### Table: proposals (added 2026-08-24, split)

The paper-approved form. One row per submitted proposal; never carries grading data.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| title | varchar(255) | No | — | Proposal title (Arabic) |
| description | text | Yes | null | Long description |
| academic_year | varchar(9) | No | — | e.g., "2024/2025" |
| department_id | bigint unsigned, FK | No | — | FK to departments.id |
| specialization_id | bigint unsigned, FK | No | — | FK to specializations.id |
| supervisor_id | bigint unsigned, FK | No | — | FK to users.id |
| created_by | bigint unsigned, FK | Yes | null | FK to users.id (nullOnDelete) |
| draft_file_path | varchar(255) | Yes | null | Path to uploaded PDF in public storage |
| status_id | tinyint unsigned, FK | No | — | FK to project_status.id (2-state مقترح/مؤرشف lifecycle) |
| based_on_project_id | bigint unsigned | Yes | null | Points at projects.id (no DB-level FK constraint) — "a proposal building on a previously finished project" |
| is_deleted | boolean | No | false | Soft delete flag |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** `department_id` to departments, `specialization_id` to specializations, `supervisor_id` to users, `created_by` to users.id (nullOnDelete), `status_id` to project_status.id. `based_on_project_id` has no DB constraint (points at the new `projects` table created by a later migration in the same batch).

### Table: projects (rebuilt 2026-08-24, split)

The actual in-progress/graded work. `belongsTo` a proposal; reads supervisor/students by reference
through `$project->proposal`, never duplicates them onto this table.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| proposal_id | bigint unsigned, FK, unique | No | — | FK to proposals.id (restrictOnDelete) — one project per proposal |
| status_id | tinyint unsigned, FK | No | — | FK to project_lifecycle_status.id (2-state قيد التنفيذ/مؤرشف lifecycle — NOT the same table as proposals.status_id) |
| final_score | decimal(5,2) | Yes | null | Final score out of 100 |
| final_file_path | varchar(255) | Yes | null | Path to the uploaded final project PDF, set by ProjectController::finalize() |
| instantiated_by | bigint unsigned, FK | Yes | null | FK to users.id (nullOnDelete) — who ran instantiateProject(); null for legacy-migrated rows |
| instantiated_at | timestamp | Yes | null | When instantiateProject() ran |
| visit_count | int unsigned | No | 0 | Page view counter |
| is_deleted | boolean | No | false | Soft delete flag |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** `proposal_id` to proposals.id (unique, restrictOnDelete — a hard-deleted proposal must not silently cascade-destroy a graded project and its examiners/evaluations), `status_id` to project_lifecycle_status.id, `instantiated_by` to users.id (nullOnDelete).

Note (2026-08-23, pre-split): `supervisor_approved_by/_at` and `department_approved_by/_at` (added
2026-08-17) were dropped by migration `2026_08_23_120000_drop_approval_gate_columns_from_projects_table.php`
— approval turned out to happen on paper, outside the system. `created_by` was added in their
place on the old flat `projects` table; it now lives on `proposals` instead (see table above),
carrying forward the same creator-or-department-manager permission check described in CLAUDE.md's
"Key Business Rules", now enforced by `Proposal::canBeModifiedBy()`/`canBeInstantiatedBy()`.

### Table: proposal_students (added 2026-08-24, split — replaces project_students)

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| proposal_id | bigint unsigned, FK | No | — | FK to proposals.id (cascadeOnDelete) |
| full_name | varchar(255) | No | — | Student full name |
| registration_number | varchar(255) | No | — | Student registration number |
| status | varchar(255) | No | active | Student status: active, withdrawn, completed |
| withdrawal_date | date | Yes | null | Date of withdrawal if withdrawn |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** `proposal_id` references proposals.id, cascadeOnDelete.

Note: the old `project_students` table (and the old flat `projects` table, renamed to
`projects_legacy` mid-migration) were dropped by `proposals:migrate-legacy-data`'s
`dropLegacyTables()` step once it verified every row copied forward correctly — they no longer
exist in the schema.

### Table: project_lifecycle_status (added 2026-08-24, split)

Backs `projects.status_id` (the Project lifecycle) — independent from `project_status` above
(which backs `proposals.status_id`, the Proposal lifecycle). Same shape, different table, different
meaning at the same ids.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | tinyint unsigned, PK | No | auto | Primary key |
| status_name | varchar(255), unique | No | — | Status identifier string |
| sort_order | tinyint unsigned | No | 0 | Display order |
| is_active | boolean | No | true | Active flag |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Seeded statuses (from ProjectLifecycleStatusSeeder):**

| id | status_name | sort_order | is_active |
|---|---|---|---|
| 1 | قيد التنفيذ (in progress) | 1 | true |
| 2 | مؤرشف (archived) | 2 | true |

Note: `instantiateProject()` always creates a new project at id=1 (قيد التنفيذ). Moving it to id=2
(مؤرشف) previously required manual intervention — see the "Flagged limitation (closed
2026-08-25)" note under Major Changes above — but this gap is now closed by the Project
Finalize/Archive change: `POST /projects/{id}/finalize` flips the status, driven from the
finalize card in `Projects/Show.vue`.

### Table: project_documents — DROPPED 2026-08-24 (split)

Existed prior to the split (milestone document tracking). Dropped by migration
`2026_08_24_160600_drop_project_documents_table.php` — the table was empty (0 rows) at the time of
the split, confirmed via audit, so no data was lost. Milestone document tracking is not currently
implemented anywhere in the split schema; STATUS_HISTORY and DEFENSE were audited codebase-wide
prior to the 2026-08-17 lifecycle scope-down and confirmed to have never been implemented
(planning docs only, no migration/model/controller) — nothing further to remove.

### Table: examiners

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| full_name | varchar(255) | No | — | Examiner full name (Arabic) |
| title | varchar(255) | Yes | null | Academic title (Dr., Prof., etc.) |
| department_id | bigint unsigned, FK | Yes | null | FK to departments.id (nullOnDelete) |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** `department_id` references departments.id, nullOnDelete.

### Table: project_examiners (pivot)

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| project_id | bigint unsigned, FK | No | — | FK to projects.id (cascadeOnDelete) |
| examiner_id | bigint unsigned, FK | No | — | FK to examiners.id (cascadeOnDelete) |
| assigned_by | bigint unsigned, FK | Yes | null | FK to users.id (nullOnDelete) — who assigned |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Unique constraint:** (project_id, examiner_id) — no duplicate assignments.
**Foreign keys:** project_id cascadeOnDelete, examiner_id cascadeOnDelete, assigned_by nullOnDelete.

### Table: evaluations

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| project_id | bigint unsigned, FK | No | — | FK to projects.id (cascadeOnDelete) |
| examiner_id | bigint unsigned, FK | No | — | FK to examiners.id (cascadeOnDelete) |
| notes | text | No | — | Examiner evaluation notes (Arabic) |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** project_id and examiner_id both cascadeOnDelete.

### Tables created by Spatie (migration 2026_06_15_094926)

- `permissions` — Spatie permissions table
- `roles` — Spatie roles table (5 roles seeded: super_admin, dept_manager, supervisor, dept_staff, viewer)
- `model_has_permissions` — pivot
- `model_has_roles` — pivot
- `role_has_permissions` — pivot

---

## 3. Models (app/Models/)

### app/Models/User.php

- **Table:** users
- **Traits:** HasFactory, Notifiable, HasRoles (Spatie)
- **Fillable:** name, email, password, employee_number, department_id, is_active
- **Hidden:** password, remember_token
- **Casts:** email_verified_at (datetime), password (hashed), is_active (boolean)
- **Relationships:**
  - `department()` — BelongsTo(Department), FK: department_id
  - `supervisedProposals()` — HasMany(Proposal), FK: supervisor_id (renamed 2026-08-24 from
    `supervisedProjects()`/`HasMany(Project)` — now returns `Proposal` rows, since supervisor is a
    proposal-level attribute read by reference from `Project`, not stored on `projects`)

### app/Models/StaffInvitation.php (added 2026-08-29)

- **Table:** staff_invitations
- **Timestamps:** `created_at` only (`UPDATED_AT = null`)
- **Fillable:** user_id, token_hash, expires_at, used_at
- **Casts:** expires_at (datetime), used_at (datetime)
- **Relationships:** `user()` — BelongsTo(User)
- **Statics:** `issueFor(User): string` — deletes any prior row for the user, inserts a fresh one
  (`token_hash` = sha256 of a 64-char `Str::random`, `expires_at` = now +24h), returns the PLAIN
  token. `signedUrlFor(User, string $plainToken): string` — `URL::temporarySignedRoute('staff.setup-password', <that user's expires_at>, ['token' => …, 'email' => …])`.
- **Helpers:** `isExpired()`, `isUsed()`

### app/Models/Department.php

- **Table:** departments
- **Traits:** HasFactory
- **Fillable:** name, code, description
- **Relationships:**
  - `specializations()` — HasMany(Specialization)
  - `users()` — HasMany(User)
  - `proposals()` — HasMany(Proposal) (renamed 2026-08-24 from `projects()`/`HasMany(Project)`)
  - `examiners()` — HasMany(Examiner)

### app/Models/Specialization.php

- **Table:** specializations
- **Traits:** HasFactory
- **Fillable:** name, department_id
- **Relationships:**
  - `department()` — BelongsTo(Department)
  - `proposals()` — HasMany(Proposal) (renamed 2026-08-24 from `projects()`/`HasMany(Project)`)

### app/Models/Proposal.php (added 2026-08-24, split)

The paper-approved form. See Section 2's `proposals` table and CLAUDE.md's "Key Business Rules"
for the full lifecycle/permission narrative.

- **Table:** proposals
- **Traits:** HasFactory
- **Constants:** `STATUS_ARCHIVED = 1`, `STATUS_PENDING = 2` (backs `project_status.id` — same IDs
  the old flat `Project` model used pre-split, now living here instead)
- **Fillable:** title, description, academic_year, department_id, specialization_id, supervisor_id, created_by, draft_file_path, status_id, based_on_project_id, is_deleted
- **Casts:** is_deleted (boolean)
- **Relationships:**
  - `department()` — BelongsTo(Department)
  - `specialization()` — BelongsTo(Specialization)
  - `supervisor()` — BelongsTo(User), FK: supervisor_id
  - `createdBy()` — BelongsTo(User), FK: created_by
  - `status()` — BelongsTo(ProjectStatus), FK: status_id
  - `basedOnProject()` — BelongsTo(Project), FK: based_on_project_id (points at the *new* `projects`
    table — "a proposal building on a previously finished project", not another proposal)
  - `students()` — HasMany(ProposalStudent)
  - `instantiatedProject()` — HasOne(Project)
- **Helper methods:**
  - `isEditable(): bool` — `status_id === STATUS_PENDING`
  - `isArchived(): bool` — `status_id === STATUS_ARCHIVED`
- **Permission methods (ported from the old flat `Project::canBeModifiedBy()`/`canBeArchivedBy()`):**
  - `canBeModifiedBy(User $user): bool` — true for super_admin unconditionally; otherwise requires
    `status_id === STATUS_PENDING` AND (dept_manager of this proposal's department, OR the creator
    (`created_by === $user->id`) also in this proposal's department). Backs replace/delete.
  - `canBeInstantiatedBy(User $user): bool` — true for super_admin unconditionally; otherwise
    requires dept_manager of this proposal's department AND `status_id === STATUS_PENDING`.
- **`instantiateProject(User $actor): Project`** — the single atomic action, wrapped in
  `DB::transaction()`: updates `status_id` to `STATUS_ARCHIVED`, then creates the linked `Project`
  row via `instantiatedProject()->create(['status_id' => Project::STATUS_IN_PROGRESS,
  'instantiated_by' => $actor->id, 'instantiated_at' => now()])`. Either both happen or neither
  does (see Section 11's InstantiateProjectTest/ProposalModelTest for the rollback proof).

### app/Models/Project.php (full rewrite, 2026-08-24 split)

The actual in-progress/graded work. See Section 2's `projects` table.

- **Table:** projects
- **Traits:** HasFactory
- **Constants:** `STATUS_IN_PROGRESS = 1`, `STATUS_ARCHIVED = 2` (backs
  `project_lifecycle_status.id` — a **different** table/meaning than `Proposal::STATUS_ARCHIVED`/
  `STATUS_PENDING` above, which back `project_status.id`, despite the same numeric-looking pattern)
- **Fillable:** proposal_id, status_id, final_score, instantiated_by, instantiated_at, visit_count, is_deleted
- **`$appends`:** `['supervisor', 'students']` — computed accessors, never real columns (see below);
  fired automatically on every JSON serialization of a Project, including inside paginated lists
- **Casts:** is_deleted (boolean), final_score (decimal:2), instantiated_at (datetime)
- **Relationships:**
  - `proposal()` — BelongsTo(Proposal)
  - `instantiatedBy()` — BelongsTo(User), FK: instantiated_by
  - `status()` — BelongsTo(ProjectLifecycleStatus), FK: status_id
  - `examiners()` — BelongsToMany(Examiner, pivot: project_examiners), using ProjectExaminer pivot model, withPivot('assigned_by'), withTimestamps()
  - `evaluations()` — HasMany(Evaluation)
- **Read-by-reference accessors** (`Attribute::get`, read-only; proxy through `proposal` instead of
  duplicating data onto `projects` — callers must eager-load `proposal.supervisor`/
  `proposal.students` to avoid an N+1, since both accessors fire on every row):
  - `supervisor()` — `$this->proposal?->supervisor`
  - `students()` — `$this->proposal?->students ?? collect()`
- **Removed from the old flat model:** `department()`/`specialization()`/`createdBy()`/
  `currentStatus()`/`basedOn()`/`documents()` relationships and the `canBeModifiedBy()`/
  `canBeArchivedBy()` permission methods no longer exist on `Project` — department/specialization/
  creator are proposal-level facts read via `->proposal`, and the permission methods moved to
  `Proposal` (see above) since only proposals are modified/instantiated now, never projects
  directly.

### app/Models/ProjectStatus.php

- **Table:** project_status (non-standard table name, set explicitly with `$table = 'project_status'`)
- **Fillable:** status_name, sort_order, is_active
- **Casts:** is_active (boolean)
- **Relationships:**
  - `proposals()` — HasMany(Proposal), FK: status_id (fixed during the Task 8 code review — was
    still `projects()` → HasMany(Project) via `current_status_id`, a column that no longer exists
    on `projects`; `ProjectStatus` backs `proposals.status_id` post-split, so it needed a
    `proposals()` relation, not a dead `projects()` one)

### app/Models/ProjectLifecycleStatus.php (added 2026-08-24, split)

Reference table model for `projects.status_id` (the Project lifecycle) — independent from
`ProjectStatus` above (see Section 2's note on the two tables).

- **Table:** project_lifecycle_status (explicit `$table`)
- **Fillable:** status_name, sort_order, is_active
- **Casts:** is_active (boolean)
- **Relationships:**
  - `projects()` — HasMany(Project), FK: status_id

### app/Models/ProposalStudent.php (added 2026-08-24, split — replaces ProjectStudent)

- **Table:** proposal_students
- **Fillable:** proposal_id, full_name, registration_number, status, withdrawal_date
- **Casts:** withdrawal_date (date)
- **Relationships:**
  - `proposal()` — BelongsTo(Proposal)

### Removed models (2026-08-24, split; files deleted during the Task 8 code review)

- **`app/Models/ProjectStudent.php`** and **`app/Models/ProjectDocument.php`** — their backing
  tables (`project_students`, `project_documents`) were both dropped by the split (see Section 2).
  Left on disk as dead code after the split (confirmed unreferenced anywhere in `app/`,
  `resources/js/`, `routes/`, `database/`, or `tests/`); both `.php` files were deleted as part of
  the Task 8 code review.

### app/Models/ProjectExaminer.php

- **Table:** project_examiners
- **Extends:** Pivot (not Model — this is a pivot model used in BelongsToMany)
- **Fillable:** project_id, examiner_id, assigned_by
- **Relationships:**
  - `project()` — BelongsTo(Project)
  - `examiner()` — BelongsTo(Examiner)

### app/Models/Examiner.php

- **Table:** examiners
- **Traits:** HasFactory
- **Fillable:** full_name, title, department_id
- **Relationships:**
  - `department()` — BelongsTo(Department)
  - `projects()` — BelongsToMany(Project, pivot: project_examiners), using ProjectExaminer, withPivot('assigned_by'), withTimestamps()

### app/Models/Evaluation.php

- **Table:** evaluations
- **Fillable:** project_id, examiner_id, notes
- **Relationships:**
  - `project()` — BelongsTo(Project)
  - `examiner()` — BelongsTo(Examiner)

---

## 4. Controllers (app/Http/Controllers/)

### app/Http/Controllers/Controller.php

Base controller. Empty — extends Laravel's base Controller.

### app/Http/Controllers/Auth/SetupPasswordController.php (added 2026-08-29)

**Purpose:** the guest-only staff account-activation flow (invite email → set password → login).

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| create() | GET /setup-password/{token} | token, ?email | `resolveValidInvitation()`; on success renders the form with the user's read-only name/email/role + `submitUrl` = the current signed URL | Inertia: auth/SetupPassword or auth/InvitationInvalid | guest |
| store() | POST /setup-password/{token} | SetupPasswordRequest, token | `resolveValidInvitation()`; on success `Hash::make` the password, set `is_active`+`email_verified_at`, mark the invitation `used_at`, `Auth::login`, regenerate session | Redirect /dashboard with flash, or Inertia: auth/InvitationInvalid | guest + `throttle:setup-password` (5/hour/IP) |

`resolveValidInvitation(Request, token, stage)` (protected) returns the `StaffInvitation` or `null`
on ANY failure — bad `hasValidSignature()`, unknown sha256(token), `email` mismatch (`hash_equals`),
`password` already set, `used_at` set, or `expires_at` past — and `Log::warning`s each failure and
`Log::info`s each success with `user_id`/`ip`/`outcome`, never the token.

### app/Http/Controllers/DashboardController.php

**Purpose:** Renders the dashboard with role-based statistics (this controller is present but the /dashboard route now maps to ReportController::dashboard() — confirmed unreachable via routes/web.php).

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /dashboard | Request | Builds stats array based on role | Inertia: Dashboard | All authenticated |

Role branching: super_admin gets total_users, total_projects, total_departments; dept_manager gets dept_projects count; supervisor gets supervised_projects count; default gets total_projects.

**Accuracy flag (found during this doc pass, not fixed — Task 8 is docs-only):** this controller's
internals are now stale post-split and would error if ever reachable again. `deptManagerStats()`
queries `Project::where('department_id', $user->department_id)` — `projects` has no
`department_id` column any more (it moved to `proposals`). `supervisorStats()` calls
`$user->supervisedProjects()` — renamed to `supervisedProposals()` on `User` (see Section 3). Since
`/dashboard` is routed to `ReportController::dashboard()` instead, this is dead code today, not a
live bug — flagged here for a future cleanup/deletion pass.

### app/Http/Controllers/ReportController.php

**Purpose:** Provides dashboard stats (role-based) and 4 report pages with PDF/Excel export.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| dashboard() | GET /dashboard | Request | Calls ReportService based on role | Inertia: Dashboard | auth+verified |
| departmentReport() | GET /reports/department | ?department_id | dept_manager forced to own dept; super_admin can filter | Inertia: Reports/Department | dept_manager, super_admin |
| specializationReport() | GET /reports/specializations | — | ReportService::getSpecializationTrends() | Inertia: Reports/Specializations | dept_manager, super_admin |
| supervisorReport() | GET /reports/supervisors | — | ReportService::getSupervisorReport() | Inertia: Reports/Supervisors | dept_manager, super_admin |
| yearlyReport() | GET /reports/yearly | — | ReportService::getYearlyComparisonReport() | Inertia: Reports/Yearly | dept_manager, super_admin |
| exportPdf() | GET /reports/export/pdf | ?type | Builds data, renders exports.report Blade via dompdf | PDF download | dept_manager, super_admin |
| exportExcel() | GET /reports/export/excel | ?type | Builds data, uses ReportExport class | XLSX download | dept_manager, super_admin |

### app/Http/Controllers/DepartmentController.php

**Purpose:** CRUD for departments. Department managers are restricted to their own department on edit/update.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /departments | — | withCount('specializations'), with('specializations') | Inertia: Departments/Index | super_admin, dept_manager |
| create() | GET /departments/create | — | Empty form | Inertia: Departments/Create | super_admin |
| store() | POST /departments | StoreDepartmentRequest | Department::create() | Redirect to index | super_admin |
| edit() | GET /departments/{dept}/edit | Department | Aborts 403 if dept_manager tries another dept | Inertia: Departments/Edit | super_admin, dept_manager |
| update() | PUT/PATCH /departments/{dept} | UpdateDepartmentRequest | Aborts 403 if dept_manager tries another dept | Redirect to index | super_admin, dept_manager |
| destroy() | DELETE /departments/{dept} | Department | Blocks if dept has proposals (`department->proposals()->exists()`, renamed 2026-08-24 from `->projects()`); calls dept->delete() | Redirect to index | super_admin |

### app/Http/Controllers/SpecializationController.php

**Purpose:** Create, update, delete specializations (inline in department management).

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| store() | POST /specializations | StoreSpecializationRequest | Specialization::create() | Redirect back | super_admin, dept_manager |
| update() | PUT /specializations/{spec} | UpdateSpecializationRequest | spec->update() | Redirect back | super_admin, dept_manager |
| destroy() | DELETE /specializations/{spec} | Specialization | Blocks if spec has proposals (`specialization->proposals()->exists()`, renamed 2026-08-24 from `->projects()`); spec->delete() | Redirect back | super_admin, dept_manager |

### app/Http/Controllers/ProjectController.php (full rewrite, 2026-08-24 split)

**Purpose:** Thin, read-only listing/detail of instantiated projects. No create/store/edit/update/
destroy — all project creation/replace/delete/instantiate happens on the owning `Proposal` (see
`ProposalController` below). `finalize()` (added 2026-08-25) is the one exception to this
thin-controller pattern — it's the sole write path on `Project` directly.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /projects | Request | `Project::with(['proposal.department','proposal.specialization','proposal.supervisor','proposal.students','status'])->where('is_deleted', false)->latest()->paginate(15)` | Inertia: Projects/Index | auth |
| show() | GET /projects/{id} | id | Eager-loads the same proposal relations plus `status`, `examiners.department:id,name`, `evaluations`; increments visit_count; returns `availableExaminers` (examiners not yet assigned), plus `canFinalize`/`finalizationBlockers` for the finalize card | Inertia: Projects/Show | auth |
| finalize() | POST /projects/{id}/finalize | FinalizeProjectRequest, id | Stores uploaded PDF to `projects/final` disk, sets `final_file_path` + `status_id`=STATUS_ARCHIVED | Redirect to projects.show | per `canBeFinalizedBy()` (dept_manager/dept_staff of that department, or super_admin) — enforced in the Form Request |

Note (per the controller's own inline comment): `proposal.supervisor` and `proposal.students` must
both be eager-loaded on every query even where a view doesn't render students, because
`Project::$appends = ['supervisor', 'students']` fires both accessors during JSON serialization for
every row — omitting either causes an N+1 across a whole paginated page.

### app/Http/Controllers/ProposalController.php (new, 2026-08-24 split)

**Purpose:** Full CRUD for proposals (the paper-approved form) plus `instantiate()`, the atomic
مقترح→مؤرشف + Project-creation action.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /proposals | Request (filters) | `SearchService::searchProposals()` + `getFilterOptions()` | Inertia: Proposals/Index | auth |
| create() | GET /proposals/create | — | `abort(403)` unless dept_staff/dept_manager/super_admin; passes departments, specializations, supervisors | Inertia: Proposals/Create | dept_staff, dept_manager, super_admin |
| store() | POST /proposals | StoreProposalRequest | Optional PDF upload to `storage/public/projects`; creates Proposal (`status_id`=STATUS_PENDING, `created_by`=Auth::id()); creates ProposalStudent rows; `detectSimilarity()` flash | Redirect to proposals.show | dept_staff (own dept only, enforced in the Form Request), dept_manager, super_admin |
| show() | GET /proposals/{id} | id | Eager-loads department/specialization/supervisor/students/status/createdBy/instantiatedProject | Inertia: Proposals/Show | auth |
| edit() | GET /proposals/{id}/edit | id | `abort(403)` unless `canBeModifiedBy()`; passes form data + depts/specs/supervisors | Inertia: Proposals/Edit | per `canBeModifiedBy()` |
| update() | PUT /proposals/{id} | UpdateProposalRequest | Replaces PDF if provided (deletes old file first); replaces students (delete-all then recreate); `detectSimilarity()` flash | Redirect to proposals.show | per `canBeModifiedBy()`; moving to a different department via replace is super_admin-only even for a passing dept_manager (enforced in `UpdateProposalRequest`) |
| destroy() | DELETE /proposals/{id} | DeleteProposalRequest, id | Sets `is_deleted`=true | Redirect to proposals.index | per `canBeModifiedBy()` |
| instantiate() | POST /proposals/{id}/instantiate | InstantiateProjectRequest, id | Calls `Proposal::instantiateProject($user)` — atomic مقترح→مؤرشف + creates linked Project | Redirect to projects.show | per `canBeInstantiatedBy()` (dept_manager of that department, or super_admin) |

**Form Requests** (all in `app/Http/Requests/`): `StoreProposalRequest` (role + dept_staff-own-dept
check), `UpdateProposalRequest` (`canBeModifiedBy()` + cross-department super_admin-only guard),
`DeleteProposalRequest` (`canBeModifiedBy()`), `InstantiateProjectRequest` (`canBeInstantiatedBy()`
— its route parameter is named `id`, not `proposal`, since `proposals/{id}/instantiate` is defined
outside the `Route::resource()` call; see Section 5). These replace the old flat model's
`StoreProjectRequest`/`UpdateProjectRequest`/`DeleteProjectRequest`/`ArchiveProjectRequest`.

### app/Http/Controllers/SearchController.php

**Purpose:** Global search page and autocomplete suggestions endpoint — now searches `Proposal`
rows, not `Project`.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /search | Request (filters) | `SearchService::searchProposals()`; loads `students` (id, full_name, proposal_id) via `loadMissing` | Inertia: Search/Index | auth |
| suggestions() | GET /search/suggestions | ?q=string | Returns up to 5 `Proposal` titles matching query (min 2 chars), ordered by `->latest()` — **not** by visit_count any more (`Proposal` has no `visit_count` column; that field lives on `Project` now, see Section 2) | JSON array | auth |

### app/Http/Controllers/ExaminerController.php

**Purpose:** CRUD for examiner records with optional department filter. Unchanged by the split
(verified against current source) — `examiner->projects()` still resolves correctly since
`project_examiners` still attaches to `projects`, unaffected by the split.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /examiners | ?department_id | withCount('projects'); optional dept filter | Inertia: Examiners/Index | super_admin, dept_manager |
| store() | POST /examiners | StoreExaminerRequest | Examiner::create() | Redirect to index | super_admin, dept_manager |
| update() | PUT /examiners/{examiner} | UpdateExaminerRequest | examiner->update() | Redirect to index | super_admin, dept_manager |
| destroy() | DELETE /examiners/{examiner} | Examiner | Blocks if examiner has projects; examiner->delete() | Redirect to index | super_admin, dept_manager |

### app/Http/Controllers/ProjectExaminerController.php

**Purpose:** Assign and remove examiners from projects (max 2 per project enforced).

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| assign() | POST /projects/{id}/assign-examiner | int $projectId, AssignExaminerRequest | Checks max 2 cap; checks duplicate; attaches with assigned_by=Auth::id() | Redirect back | super_admin, dept_manager |
| remove() | DELETE /projects/{id}/examiners/{examinerId} | int $projectId, int $examinerId | Detaches examiner from project | Redirect back | super_admin, dept_manager |

Both `assign()` and `remove()` now reject the request with a flash error ("لا يمكن التعديل على
مشروع مؤرشف نهائيًا") if the project's `status_id === Project::STATUS_ARCHIVED` (added 2026-08-25,
alongside the finalize flow).

### app/Http/Controllers/EvaluationController.php

**Purpose:** Add evaluation notes per examiner and update a project's final score.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| store() | POST /projects/{id}/evaluation | int $projectId, StoreEvaluationRequest | project->evaluations()->create(validated) | Redirect back | super_admin, dept_manager |
| updateScore() | PATCH /projects/{id}/score | int $projectId, UpdateScoreRequest | project->update(['final_score' => ...]) | Redirect back | super_admin, dept_manager |

Both `store()` and `updateScore()` now reject the request with a flash error ("لا يمكن التعديل على
مشروع مؤرشف نهائيًا") if the project's `status_id === Project::STATUS_ARCHIVED` (added 2026-08-25,
alongside the finalize flow).

### app/Http/Controllers/ImportController.php

**Purpose:** 4-step bulk import wizard: template download, preview (dry run), actual import, ZIP PDF upload.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /import | — | Renders wizard page | Inertia: Import/Index | super_admin |
| downloadTemplate() | GET /import/template | — | Returns ProjectImportTemplate as XLSX | XLSX download | super_admin |
| preview() | POST /import/preview | file (xlsx, max 5120KB) | ProjectsImport(dryRun:true); flashes preview summary | Redirect back | super_admin |
| import() | POST /import/run | file (xlsx, max 5120KB) | ProjectsImport(dryRun:false); flashes import_summary | Redirect back | super_admin |
| uploadPdfs() | POST /import/pdfs | zip_file (zip, max 51200KB) | Extracts ZIP; matches PDF filenames to `Proposal::title`; saves to public storage; sets `draft_file_path` on the matched proposal; flashes pdf_summary | Redirect back | super_admin |

The controller itself (index/downloadTemplate/preview/import) is unchanged and still accurate.
`app/Imports/ProjectsImport.php` (invoked by preview()/import()) **was** rewritten for the split:
each valid row now creates a `Proposal` and immediately calls `$proposal->instantiateProject($actor)`,
then unconditionally sets the resulting `Project` to `STATUS_ARCHIVED` (with `final_score` applied
if present, null otherwise) — matching the old importer's "create it pre-archived" behavior for
*all* bulk-imported historical work, not just rows that happen to carry a score (fixed during the
Task 8 code review — the row previously only archived when `final_score` was present, silently
leaving ungraded imported rows invisible on the public site). See Section 6 (ProjectsImport isn't
itself a Service class, but this is where its logic is documented since Section 4 covers the
controller that drives it).

`uploadPdfs()` was also fixed during the Task 8 code review: it previously called
`Project::where('project_title', $baseName)` and, on a match, `$project->update(['draft_file_path'
=> ...])` and `$project->documents()->create(...)` — none of `project_title`, `draft_file_path`, or
the `documents()` relationship exist on the post-split `Project` model (`project_title`/
`draft_file_path` moved to `Proposal`; `documents()` and the `project_documents` table were dropped
entirely), so the first real ZIP upload would have thrown an "unknown column" error. Now matches
against `Proposal::title` and sets `draft_file_path` directly on the proposal, with no document
record created (there is nothing to create — the table is gone).

### app/Http/Controllers/PublicController.php

**Purpose:** Public-facing pages (no authentication required) — now query `Project`, filtered to
`Project::STATUS_ARCHIVED`, eager-loading `proposal.*` for the title/description/dept/spec/
supervisor/students that used to live directly on the old flat `Project`.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET / | — | Counts `Project::where('status_id', Project::STATUS_ARCHIVED)->where('is_deleted', false)`, depts, specs for landing page stats | Inertia: Welcome | Public |
| browse() | GET /browse | ?search, ?department_id, ?specialization_id, ?academic_year | `Project::query()->where('status_id', Project::STATUS_ARCHIVED)->where('is_deleted', false)->with(['proposal.department','proposal.specialization','proposal.supervisor','proposal.students'])`; search/dept/spec/year filters all applied via `whereHas('proposal', ...)`; paginates 12 per page; `years` filter option pulled from `Proposal::whereHas('instantiatedProject', fn ($q) => $q->where('status_id', Project::STATUS_ARCHIVED))` | Inertia: Public/Browse | Public |
| show() | GET /browse/{id} | int $id | Loads only an archived, non-deleted `Project` (`proposal.*`, `examiners`, `evaluations`); increments visit_count; fetches 3 related archived Projects sharing `proposal.specialization_id` | Inertia: Public/Show | Public |

### app/Http/Controllers/Admin/UserController.php

**Purpose:** Admin panel for managing all users (super_admin only).

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /admin/users | ?search, ?role, ?department_id, ?is_active | Paginated + filtered users; returns as {data, links, meta} | Inertia: Admin/Users/Index | super_admin |
| store() | POST /admin/users | StoreUserRequest | User::create(); assignRole | Redirect to index | super_admin |
| update() | PUT /admin/users/{user} | UpdateUserRequest | user->update(); syncRoles; optional password change | Redirect to index | super_admin |
| toggleActive() | PATCH /admin/users/{user}/toggle-active | User | Flips is_active boolean | Redirect back | super_admin |
| destroy() | DELETE /admin/users/{user} | User | Refuses if only super_admin left; user->delete() | Redirect to index | super_admin |

### Auth Controllers (app/Http/Controllers/Auth/)

| File | Purpose |
|---|---|
| AuthenticatedSessionController.php | Login / Logout |
| RegisteredUserController.php | Registration (disabled — redirects to login with error flash) |
| PasswordResetLinkController.php | Send password reset email |
| NewPasswordController.php | Reset password via token |
| EmailVerificationPromptController.php | Show verification prompt |
| EmailVerificationNotificationController.php | Resend verification email |
| VerifyEmailController.php | Verify email via signed URL link |
| ConfirmablePasswordController.php | Password confirmation before sensitive actions |

### Settings Controllers (app/Http/Controllers/Settings/)

| File | Purpose |
|---|---|
| ProfileController.php | Update profile name and email |
| PasswordController.php | Update password |

---

## 5. Routes (routes/web.php)

| Method | URL | Controller@Method | Middleware | Roles |
|---|---|---|---|---|
| GET | / | PublicController@index | none | Public |
| GET | /browse | PublicController@browse | none | Public |
| GET | /browse/{id} | PublicController@show | none | Public |
| GET | /design-system | Closure (DesignSystem) | none | Public (dev-only) |
| GET | /dashboard | ReportController@dashboard | auth, verified | All authenticated |
| GET | /reports/department | ReportController@departmentReport | auth, role:dept_manager,super_admin | dept_manager, super_admin |
| GET | /reports/specializations | ReportController@specializationReport | auth, role:dept_manager,super_admin | dept_manager, super_admin |
| GET | /reports/supervisors | ReportController@supervisorReport | auth, role:dept_manager,super_admin | dept_manager, super_admin |
| GET | /reports/yearly | ReportController@yearlyReport | auth, role:dept_manager,super_admin | dept_manager, super_admin |
| GET | /reports/export/pdf | ReportController@exportPdf | auth, role:dept_manager,super_admin | dept_manager, super_admin |
| GET | /reports/export/excel | ReportController@exportExcel | auth, role:dept_manager,super_admin | dept_manager, super_admin |
| GET | /admin | Closure (Admin/Dashboard render) | auth, role:super_admin | super_admin |
| GET | /admin/users | AdminUserController@index | auth, role:super_admin | super_admin |
| POST | /admin/users | AdminUserController@store | auth, role:super_admin | super_admin |
| PUT | /admin/users/{user} | AdminUserController@update | auth, role:super_admin | super_admin |
| DELETE | /admin/users/{user} | AdminUserController@destroy | auth, role:super_admin | super_admin |
| PATCH | /admin/users/{user}/toggle-active | AdminUserController@toggleActive | auth, role:super_admin | super_admin |
| GET | /departments | DepartmentController@index | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| GET | /departments/{dept}/edit | DepartmentController@edit | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| PUT | /departments/{dept} | DepartmentController@update | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| PATCH | /departments/{dept} | DepartmentController@update | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| POST | /specializations | SpecializationController@store | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| PUT | /specializations/{spec} | SpecializationController@update | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| DELETE | /specializations/{spec} | SpecializationController@destroy | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| GET | /examiners | ExaminerController@index | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| POST | /examiners | ExaminerController@store | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| PUT | /examiners/{examiner} | ExaminerController@update | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| DELETE | /examiners/{examiner} | ExaminerController@destroy | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| POST | /projects/{id}/assign-examiner | ProjectExaminerController@assign | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| DELETE | /projects/{id}/examiners/{examinerId} | ProjectExaminerController@remove | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| POST | /projects/{id}/evaluation | EvaluationController@store | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| PATCH | /projects/{id}/score | EvaluationController@updateScore | auth, role:super_admin,dept_manager | super_admin, dept_manager |
| GET | /proposals | ProposalController@index | auth | All authenticated |
| POST | /proposals | ProposalController@store | auth | dept_staff (own dept), dept_manager, super_admin (checked in StoreProposalRequest) |
| GET | /proposals/create | ProposalController@create | auth | dept_staff, dept_manager, super_admin (checked in controller) |
| GET | /proposals/{proposal} | ProposalController@show | auth | All authenticated |
| GET | /proposals/{proposal}/edit | ProposalController@edit | auth | Per `Proposal::canBeModifiedBy()` (checked in controller) |
| PUT/PATCH | /proposals/{proposal} | ProposalController@update | auth | Per `Proposal::canBeModifiedBy()` (checked in UpdateProposalRequest) |
| DELETE | /proposals/{proposal} | ProposalController@destroy | auth | Per `Proposal::canBeModifiedBy()` (checked in DeleteProposalRequest) |
| POST | /proposals/{id}/instantiate | ProposalController@instantiate | auth | dept_manager of that proposal's department while مقترح, or super_admin (checked in InstantiateProjectRequest; route name `proposals.instantiate`) |
| GET | /projects | ProjectController@index | auth | All authenticated |
| GET | /projects/{id} | ProjectController@show | auth | All authenticated |
| GET | /search | SearchController@index | auth | All authenticated |
| GET | /search/suggestions | SearchController@suggestions | auth | All authenticated |
| GET | /departments/create | DepartmentController@create | auth, role:super_admin | super_admin |
| POST | /departments | DepartmentController@store | auth, role:super_admin | super_admin |
| DELETE | /departments/{dept} | DepartmentController@destroy | auth, role:super_admin | super_admin |
| GET | /import | ImportController@index | auth, role:super_admin | super_admin |
| GET | /import/template | ImportController@downloadTemplate | auth, role:super_admin | super_admin |
| POST | /import/preview | ImportController@preview | auth, role:super_admin | super_admin |
| POST | /import/run | ImportController@import | auth, role:super_admin | super_admin |
| POST | /import/pdfs | ImportController@uploadPdfs | auth, role:super_admin | super_admin |

`/proposals` is a full `Route::resource('proposals', ProposalController::class)` (index/create/
store/show/edit/update/destroy) plus the standalone `POST proposals/{id}/instantiate` route (name
`proposals.instantiate`) — together these replace the old flat model's `Route::resource('projects',
...)` + `POST projects/{id}/archive`. `/projects` is now thin: only `GET projects` (name
`projects.index`) and `GET projects/{id}` (name `projects.show`) remain — no create/store/edit/
update/destroy on `Project` at all. The examiner/evaluation/score routes
(`projects/{id}/assign-examiner` etc.) are unchanged by the split — still declared under
`projects/{id}`, since examiners/evaluations attach to an instantiated `Project`, not a `Proposal`.

Auth routes (login, logout, password reset, email verification) are defined in routes/auth.php. Registration is disabled — POST /register redirects to /login with an error flash.

---

## 6. Services (app/Services/)

### app/Services/SearchService.php

**Purpose:** Encapsulates all proposal search, filter, and similarity detection logic. Renamed and
retargeted from `Project` to `Proposal` by the 2026-08-24 split (proposals are the searchable/
listable entity; instantiated projects are found by drilling into a proposal's result, not by
searching directly).

| Method | Parameters | Return | Description |
|---|---|---|---|
| searchProposals(array $filters) | filters: search, department_id, specialization_id, academic_year, supervisor_id, status, sort | LengthAwarePaginator (15 per page) | Renamed from `searchProjects()`. Builds a `Proposal` query; applies all filters (`search` LIKE on title/description; department/specialization/academic_year/supervisor exact match; `status === 'active'` filters to `status_id === Proposal::STATUS_ARCHIVED`); eager-loads department/specialization/supervisor/status; `withCount('students')`. Sort options: `title`, default `->latest()` (created_at) — the old `visit_count` sort option no longer applies, since `Proposal` has no `visit_count` column (that field lives on `Project` now) |
| detectSimilarity(string $title, ?int $excludeId) | title string, optional excludeId to skip on edit | Collection of up to 5 Proposals | LIKE query on `title` (not `project_title`); excludes current proposal when editing; returns id, title, academic_year, department_id with department relation |
| getFilterOptions() | — | array | Returns departments (with nested specializations), academic_years (distinct, from `Proposal`, ordered desc), supervisors (role=supervisor) |

### app/Services/ReportService.php

**Purpose:** Generates all statistical data for reports and dashboard. Full rewrite for the split —
proposal-shaped stats (counts, breakdowns) now query `Proposal` directly; anything score-based
joins `projects` → `proposals` since `final_score` lives on `Project`.

| Method | Parameters | Return | Description |
|---|---|---|---|
| getDashboardStats() | — | array | `total_projects` (really a `Proposal::where('is_deleted', false)->count()`, kept as the key name for frontend compatibility), total_departments, projects_this_year (current year LIKE on `Proposal.academic_year`), pending_approvals (`Proposal::where('status_id', Proposal::STATUS_PENDING)`), recent_projects (last 5 `Proposal` rows with department/specialization/status), by_status (grouped `Proposal` counts joined with `project_status`) |
| getDepartmentReport(?int $departmentId) | Optional dept filter | array with keys: departments, supervisors | Per-dept `project_count` via `Department::withCount('proposals as project_count', ...)`; `avg_score`/`scored_count` via `Project::query()->join('proposals', 'projects.proposal_id', '=', 'proposals.id')->whereNotNull('projects.final_score')` grouped by `proposals.department_id`; supervisors via `User::role('supervisor')->withCount('supervisedProposals as project_count', ...)` |
| getSpecializationTrends() | — | array with keys: top_specializations, rare_specializations, by_year | Top 10 / bottom 5 specializations by `withCount('proposals as project_count', ...)`; `by_year` grouped from `Proposal` joined to `specializations` |
| getSupervisorReport() | — | array | All supervisors (`role('supervisor')`) with `project_count` via `supervisedProposals`, `avg_score`/`scored_count` via the same `projects`⋈`proposals` join as `getDepartmentReport()` grouped by `proposals.supervisor_id`, `by_year` breakdown grouped from `Proposal` |
| getYearlyComparisonReport() | — | array with keys: yearly, department_by_year | Per-year `Proposal` count + growth % calculation; dept×year matrix via `Proposal` joined to `departments` |

---

## 7. Vue Pages (resources/js/Pages/)

### resources/js/Pages/Welcome.vue

- **Purpose:** Arabic landing page with hero section, stats row, feature grid, and CTA buttons
- **URL:** GET /
- **Props:** `stats: { total_projects, total_departments, total_specializations }`
- **Interactions:** "تصفح المشاريع" button links to /browse; "تسجيل الدخول" links to /login
- **Roles:** Public (no auth)

### resources/js/Pages/Dashboard.vue

- **Purpose:** Role-based dashboard with stats cards, recent proposals table, status bar chart, and report quick-links
- **URL:** GET /dashboard
- **Props:** `stats: DashboardStats` (structure varies by role); `stats.recent_projects` entries are
  now `Proposal`-shaped (`{ id, title, academic_year, department, status }`) — since the split,
  **not** the old flat `Project`-shaped (`project_title`/`current_status`)
- **Interactions:**
  - super_admin: 4 StatsCard components + recent proposals table (rows link to `/proposals/{id}`) + status bar chart + 4 report quick-links
  - dept_manager: 3 stats + specializations table + supervisors list + 4 report quick-links
  - default: 1 stat card + link to /projects
- **Roles:** All authenticated

### resources/js/Pages/Departments/Index.vue

- **Purpose:** Department list with expandable rows for inline specialization management
- **URL:** GET /departments
- **Props:** `departments: Department[]` (includes specializations_count and specializations array)
- **Interactions:** Expand/collapse row shows specializations; super_admin: "Add dept" button, per-row Edit/Delete/"+Spec"; dept_manager: Edit own dept + manage own specs only (ownership checked via auth user's department_id)
- **Roles:** super_admin, dept_manager

### resources/js/Pages/Departments/Create.vue

- **Purpose:** Form to create a new department
- **URL:** GET /departments/create
- **Props:** None
- **Interactions:** Name, code, and description fields; POSTs to departments.store
- **Roles:** super_admin

### resources/js/Pages/Departments/Edit.vue

- **Purpose:** Form to edit an existing department
- **URL:** GET /departments/{id}/edit
- **Props:** `department: Department` (with specializations)
- **Interactions:** Edit name/code/description; PUT to departments.update
- **Roles:** super_admin, dept_manager (own dept only)

### resources/js/Pages/Proposals/Index.vue (renamed 2026-08-24 from Projects/Index.vue, split)

- **Purpose:** Paginated proposal list with search, advanced filters, active filter chips, and the
  "تنزيل المشروع" instantiate action
- **URL:** GET /proposals
- **Props:** `proposals: PaginatedProposals`, `filterOptions: FilterOptions`, `filters: object`
- **Interactions:** SearchBar with debounced autocomplete (via `search.suggestions`); FilterPanel
  with cascaded dept/spec/year/supervisor/sort; active filter chips (clickable × to remove);
  pagination; status badge via `@/composables/useProposalStatus` (`statusColor`/`statusLabel`/
  `isProposalStatus`); per-row role-based buttons: عرض (always), تعديل (`canReplace`, mirrors
  `Proposal::canBeModifiedBy()`), **تنزيل المشروع** (`canInstantiate` — dept_manager of that
  proposal's department while مقترح, or super_admin; opens a `ConfirmDelete`-styled confirm dialog,
  then `router.post(route('proposals.instantiate', id))`), حذف (`canDeleteProject`, same rule as
  تعديل); SimilarityWarning banner from `flash.similarity_warning`
- **Roles:** All authenticated (role-based button visibility); create button (`+ إضافة مقترح جديد`)
  shown only to dept_staff/dept_manager/super_admin

### resources/js/Pages/Proposals/Create.vue (renamed 2026-08-24 from Projects/Create.vue, split)

- **Purpose:** Form to create a new proposal
- **URL:** GET /proposals/create
- **Props:** `departments`, `specializations`, `supervisors`
- **Interactions:** Title, description, academic_year inputs; cascaded department/specialization select; dynamic students add/remove rows; PDF file upload; `form.post(route('proposals.store'), { forceFormData: true })`
- **Roles:** dept_staff, dept_manager, super_admin

### resources/js/Pages/Proposals/Edit.vue (renamed 2026-08-24 from Projects/Edit.vue, split)

- **Purpose:** Form to edit an existing proposal
- **URL:** GET /proposals/{id}/edit
- **Props:** `proposal` (with `students`), `departments`, `specializations`, `supervisors`
- **Interactions:** Pre-filled form; current PDF (`draft_file_path`) shown with download link;
  option to replace PDF; `form.put(route('proposals.update', id), { forceFormData: true })`. Note:
  `Edit.vue` no longer shows `documents` — that relationship/table was dropped by the split (see
  Section 2/3); it never carried a real UI feature pre-split either
- **Roles:** Per `Proposal::canBeModifiedBy()` — super_admin (any, while مقترح or مؤرشف), dept_manager (own dept, only while مقترح), dept_staff (creator + own dept, only while مقترح)

### resources/js/Pages/Proposals/Show.vue (renamed 2026-08-24 from Projects/Show.vue, split)

- **Purpose:** Full proposal detail page — description, students table, PDF download, and the
  create/replace/delete/instantiate actions; no examiners/evaluations/score here any more (those
  moved to the instantiated Project's own Show page, below)
- **URL:** GET /proposals/{id}
- **Props:** `proposal: Proposal` (department/specialization/supervisor/status/createdBy/
  `instantiated_project` eager-loaded)
- **Interactions:** Status badge (`useProposalStatus`); تعديل/حذف buttons per `canModify`
  (mirrors `Proposal::canBeModifiedBy()`); **تنزيل المشروع** button per `canInstantiate`
  (dept_manager of that department while مقترح, or super_admin) opens a confirm dialog then
  `router.post(route('proposals.instantiate', id))`, redirecting server-side to the new
  `projects.show` page on success
- **Roles:** All authenticated (view); write actions gated as above

### resources/js/Pages/Projects/Index.vue (new, thin — 2026-08-24 split)

- **Purpose:** Read-only listing of instantiated projects — no search/filter/create/edit/delete;
  those all live on the Proposals pages now
- **URL:** GET /projects
- **Props:** `projects: PaginatedProjects` — each row: `id`, `final_score`, `status` (from
  `project_lifecycle_status`), and `proposal: { id, title, department, specialization, supervisor }`
- **Interactions:** Plain table (عنوان/قسم/مشرف/حالة/درجة columns, reading `project.proposal.*` for
  title/department/supervisor and `project.status`/`project.final_score` directly); simple
  prev/next-style pagination; no filters, no row action buttons at all
- **Roles:** All authenticated

### resources/js/Pages/Projects/Show.vue (new, thin + examiner/evaluation/score card — 2026-08-24 split)

- **Purpose:** Instantiated-project detail page: description/students read through the proposal,
  plus the examiner assignment, evaluation notes, and final-score card that used to live on the old
  conflated `Projects/Show.vue`
- **URL:** GET /projects/{id}
- **Props:** `project: Project` (`proposal` with department/specialization eager-loaded; flat
  `project.supervisor`/`project.students` from the `$appends` accessors; `examiners`, `evaluations`,
  `status`, `visit_count`), `availableExaminers: Examiner[]`
- **Interactions:** Reads title/description/department/specialization/academic_year via
  `project.proposal.*`, and supervisor/students via the flat `project.supervisor`/
  `project.students` (unchanged shape from before the split, now sourced by the model's read-by-
  reference accessors instead of duplicated columns); examiners/evaluations section with
  assign/remove (`AssignExaminerModal`, `ConfirmDelete`) and per-examiner evaluation notes;
  `ScoreInput` for dept_manager/super_admin or a read-only score + pass/fail badge (threshold 50)
  for everyone else; no Edit/Delete/Archive header buttons at all (that logic no longer applies to
  `Project`)
- **Roles:** All authenticated (view); examiner/evaluation/score actions restricted to dept_manager,
  super_admin (`canManage`)

### resources/js/Pages/Search/Index.vue

- **Purpose:** Global search page with results grouped by department — now receives `Proposal` rows directly, not `Project`
- **URL:** GET /search
- **Props:** `projects: PaginatedProposals` (prop name unchanged for backward compatibility with the
  page's own internals, but each row is now a flat `Proposal` — `title`, `academic_year`,
  `description`, `department`, `specialization`, `supervisor`, `status`, `students`,
  `students_count`), `filterOptions`, `filters`
- **Interactions:** SearchBar with live suggestions; project cards showing highlighted match text (v-html); student name tags; pagination; card links now point to `route('proposals.show', [project.id])` (was `projects.show`)
- **Roles:** All authenticated

### resources/js/Pages/Examiners/Index.vue

- **Purpose:** Examiner list and management table
- **URL:** GET /examiners
- **Props:** `examiners` (with department, projects_count), `departments`, `filters`
- **Interactions:** Department filter select; Add examiner button opens add modal; Edit/Delete per row
- **Roles:** super_admin, dept_manager

### resources/js/Pages/Import/Index.vue

- **Purpose:** 4-step bulk import wizard
- **URL:** GET /import
- **Props:** None (receives flash props: preview, import_summary, pdf_summary via Inertia shared data)
- **Interactions:**
  - Step 1: Download template button + 13-column reference table
  - Step 2: File upload POST to preview; stats cards + first 10 rows table with pass/fail markers + full error list
  - Step 3: Confirm import POST; results (success/fail counts) + failed rows table + client-side CSV error download
  - Step 4: ZIP file upload; matched count + unmatched file names
- **Roles:** super_admin

### resources/js/Pages/Reports/Department.vue

- **Purpose:** Department report page
- **URL:** GET /reports/department
- **Props:** `report`, `departments`, `filter`
- **Interactions:** super_admin can filter by department (router.get); summary cards; departments table; per-dept specialization breakdown with percentage bars; supervisors table; ExportButtons for PDF and Excel
- **Roles:** dept_manager, super_admin

### resources/js/Pages/Reports/Specializations.vue

- **Purpose:** Specialization trends report
- **URL:** GET /reports/specializations
- **Props:** `report: { top_specializations, rare_specializations, by_year }`
- **Interactions:** Top-10 CSS bar chart; year-trend table with mini bars; rarely-used specs table; ExportButtons
- **Roles:** dept_manager, super_admin

### resources/js/Pages/Reports/Supervisors.vue

- **Purpose:** Supervisor report with client-side sorting
- **URL:** GET /reports/supervisors
- **Props:** `report: []` (array of supervisor objects with by_year data)
- **Interactions:** Client-side dept filter; 3-column sort (name/project_count/avg_score with toggle); by_year year chips per supervisor; ExportButtons
- **Roles:** dept_manager, super_admin

### resources/js/Pages/Reports/Yearly.vue

- **Purpose:** Yearly comparison report
- **URL:** GET /reports/yearly
- **Props:** `report: { yearly, department_by_year }`
- **Interactions:** Year comparison table with growth% and TrendingUp/TrendingDown icons; dept×year matrix table; summary cards; ExportButtons
- **Roles:** dept_manager, super_admin

### resources/js/Pages/Admin/Users/Index.vue

- **Purpose:** User management page (admin panel)
- **URL:** GET /admin/users
- **Props:** `users: { data, links, meta }`, `roles`, `departments`, `filters`
- **Interactions:** Search with 400ms debounce + role/dept/is_active filter selects; users table with color-coded role badges (super_admin=red, dept_manager=blue, supervisor=green, dept_staff=yellow, viewer=gray) and active/inactive badges; Edit opens EditUserModal; toggle active via router.patch; delete via ConfirmDelete; Create opens CreateUserModal
- **Roles:** super_admin

### resources/js/Pages/Public/Browse.vue

- **Purpose:** Public project browse page — no authentication required
- **URL:** GET /browse
- **Props:** `projects: Paginated<Project>`, `departments`, `specializations`, `years`, `filters` —
  each `Project` row now reads title/academic_year/department/specialization through
  `project.proposal.*`, while `project.supervisor`/`project.students` stay flat (via the model's
  `$appends` accessors) — unchanged shape from before the split
- **Interactions:** Header with college logo and login button; search input + dept/spec/year filter selects; Apply/Reset buttons; 3-column project card grid; pagination; empty state with reset button
- **Roles:** Public (no auth required)

### resources/js/Pages/Public/Show.vue

- **Purpose:** Public project detail page — no authentication required
- **URL:** GET /browse/{id}
- **Props:** `project: Project` (with all relations), `related: Project[]` — same
  proposal.*-for-title/description/dept/spec/academic_year/draft_file_path,
  flat-supervisor/students split as Browse.vue above
- **Interactions:** Back link; project title, status badge, and visit count; info grid (dept, spec, supervisor, year); students table; description; PDF download button; related projects section (same specialization, up to 3)
- **Roles:** Public (no auth required)

### resources/js/Pages/DesignSystem.vue

- **Purpose:** Dev-only design reference showing color swatches, typography scale, button variants, card patterns
- **URL:** GET /design-system
- **Props:** None
- **Roles:** Public (dev-only — note: remove before production per CLAUDE.md)

### Auth Pages (resources/js/Pages/auth/)

| File | URL | Purpose |
|---|---|---|
| Login.vue | GET /login | Arabic RTL login form with remember-me checkbox; label order is RTL-correct |
| Register.vue | GET /register | Arabic form (registration disabled; this page redirects to login) |
| ForgotPassword.vue | GET /forgot-password | Request password reset link |
| ResetPassword.vue | GET /reset-password | Enter new password via token |
| VerifyEmail.vue | GET /verify-email | Email verification prompt with resend button |
| ConfirmPassword.vue | GET /confirm-password | Re-enter current password before sensitive operations |

### Settings Pages (resources/js/Pages/settings/)

| File | URL | Purpose |
|---|---|---|
| Profile.vue | GET /settings/profile | Update name and email |
| Password.vue | GET /settings/password | Change password |
| Appearance.vue | GET /settings/appearance | Toggle light/dark theme |

---

## 8. Vue Components (resources/js/components/)

### Custom Project-Specific Components

#### resources/js/components/SearchBar.vue

- **Purpose:** Reusable search input with live autocomplete suggestions dropdown
- **Props:** modelValue (string), placeholder (string, optional), suggestions (string[])
- **Events emitted:** update:modelValue, @search (on enter or button click), @select (when suggestion clicked), @clear
- **Used in:** Proposals/Index.vue (renamed 2026-08-24 from Projects/Index.vue — accuracy fix, this
  component moved with the search bar during the split), Search/Index.vue

#### resources/js/components/FilterPanel.vue

- **Purpose:** Collapsible advanced filter panel for project listings; also exports FilterValues type
- **Props:** departments, specializations, years, supervisors, modelValue (FilterValues)
- **Events emitted:** @filter-changed (emits FilterValues)
- **Used in:** Proposals/Index.vue (renamed 2026-08-24 from Projects/Index.vue — accuracy fix)

#### resources/js/components/SimilarityWarning.vue

- **Purpose:** Warning banner displayed when a new/edited proposal title matches existing proposal titles
- **Props:** show (boolean), similarProjects (SimilarProject[])
- **Events emitted:** @continue, @change-title
- **Used in:** Proposals/Index.vue (renamed 2026-08-24 from Projects/Index.vue — accuracy fix; driven by flash.similarity_warning from server)

#### resources/js/components/Modal.vue

- **Purpose:** Generic dialog/modal with title prop, default body slot, and named #footer slot
- **Props:** show (boolean), title (string)
- **Events emitted:** @close
- **Used in:** Departments/Index.vue, Admin/Users/Index.vue

#### resources/js/components/ConfirmDelete.vue

- **Purpose:** Confirmation dialog before deleting an item; shows item name
- **Props:** show (boolean), itemName (string | undefined)
- **Events emitted:** @confirmed, @cancelled
- **Used in:** Proposals/Index.vue, Proposals/Show.vue (both accuracy-fixed 2026-08-24 — this
  component moved with delete/instantiate-confirm dialogs from the old Projects/Index.vue and
  Projects/Show.vue during the split), Projects/Show.vue (still — now for the remove-examiner
  confirm, not delete-project), Departments/Index.vue, Examiners/Index.vue, Admin/Users/Index.vue.
  **Not** Projects/Index.vue any more — the new thin project listing has no delete action.

#### resources/js/components/AssignExaminerModal.vue

- **Purpose:** Modal for assigning an examiner to a project from a dropdown of available (unassigned) examiners
- **Props:** show (boolean), projectId (number), availableExaminers (Examiner[])
- **Events emitted:** @assigned, @cancelled
- **Used in:** Projects/Show.vue

#### resources/js/components/ScoreInput.vue

- **Purpose:** Displays current final score with pass/fail badge (pass threshold: 50) and provides an editable score form for managers
- **Props:** projectId (number), currentScore (string | null)
- **Events emitted:** none (form submission handled internally via Inertia useForm)
- **Used in:** Projects/Show.vue (rendered only for dept_manager and super_admin)

#### resources/js/components/StatsCard.vue

- **Purpose:** Dashboard statistic card with title, numeric value, Lucide icon, color scheme, and optional sub-label
- **Props:** title (string), value (number | string), icon (Vue Component), color ('blue'|'green'|'purple'|'orange'), sub (string, optional)
- **Events emitted:** none
- **Used in:** Dashboard.vue

#### resources/js/components/ExportButtons.vue

- **Purpose:** Two anchor download buttons: PDF (red button) and Excel (green button)
- **Props:** pdfUrl (string), excelUrl (string)
- **Events emitted:** none
- **Used in:** Reports/Department.vue, Reports/Specializations.vue, Reports/Supervisors.vue, Reports/Yearly.vue

#### resources/js/components/AppSidebar.vue

- **Purpose:** Main sidebar navigation component with role-based menu items using Lucide icons
- **Navigation by role:**
  - super_admin: Dashboard, Projects, Search, Departments, Examiners, Reports, Import, Users
  - dept_manager: Dashboard, Projects, Search, Departments, Examiners, Reports
  - dept_staff: Dashboard, Projects, Search
  - supervisor: Dashboard, Projects, Search
  - All roles: "تصفح المشاريع" (browse /browse) link
- **Used in:** AppSidebarLayout.vue

#### resources/js/components/AppSidebarHeader.vue

- **Purpose:** Sidebar header area with sidebar collapse/expand trigger
- **Used in:** AppSidebar.vue

#### resources/js/components/NavMain.vue

- **Purpose:** Renders the list of sidebar navigation items
- **Used in:** AppSidebar.vue

#### resources/js/components/NavUser.vue

- **Purpose:** User info and logout menu at the bottom of the sidebar footer
- **Used in:** AppSidebar.vue

#### resources/js/components/UserInfo.vue / UserMenuContent.vue

- **Purpose:** User display info and dropdown menu content
- **Used in:** NavUser.vue

#### resources/js/components/DataTable.vue

- **Purpose:** Generic table wrapper component
- **Used in:** Various pages

#### resources/js/components/Brand/Logo.vue

- **Purpose:** College logo component; renders /images/logo.png with size prop controlling image dimensions
- **Props:** size ('sm'|'md'|'lg')
- **Used in:** Public/Browse.vue, Public/Show.vue, auth/AuthSimpleLayout.vue

### Shadcn/Radix UI Primitive Components (resources/js/components/ui/)

Pre-built components from shadcn-vue, wrapping Radix Vue primitives. All are wired for the project's Tailwind CSS variable tokens.

- **avatar/**: Avatar, AvatarFallback, AvatarImage
- **breadcrumb/**: 7 breadcrumb components (Breadcrumb, BreadcrumbEllipsis, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator)
- **button/**: Button (class-variance-authority variants)
- **card/**: Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle
- **checkbox/**: Checkbox
- **collapsible/**: Collapsible, CollapsibleContent, CollapsibleTrigger
- **dialog/**: 10 dialog components
- **dropdown-menu/**: 14 dropdown menu components
- **input/**: Input
- **label/**: Label
- **navigation-menu/**: 8 navigation menu components
- **separator/**: Separator
- **sheet/**: 8 sheet/drawer components
- **sidebar/**: 22 sidebar primitive components (backing AppSidebar)
- **skeleton/**: Skeleton
- **tooltip/**: Tooltip, TooltipContent, TooltipProvider, TooltipTrigger

---

## 9. Layouts (resources/js/Layouts/)

### resources/js/Layouts/AppLayout.vue

- **Purpose:** Main authenticated layout; a thin wrapper that delegates to AppSidebarLayout
- **Props:** `breadcrumbs?: BreadcrumbItemType[]`
- **Used by:** All authenticated pages: Dashboard, Projects/*, Departments/*, Search/Index, Examiners/Index, Import/Index, Reports/*, Admin/Users/Index

### resources/js/Layouts/app/AppSidebarLayout.vue

- **Purpose:** Full sidebar layout — renders AppSidebar on the right (RTL), header with breadcrumbs, and main content slot
- **Props:** `breadcrumbs?: BreadcrumbItemType[]`
- **Used by:** AppLayout.vue

### resources/js/Layouts/app/AppHeaderLayout.vue

- **Purpose:** Header-only layout (no sidebar) — alternative option
- **Props:** breadcrumbs
- **Used by:** Not currently used by any page

### resources/js/Layouts/AuthLayout.vue

- **Purpose:** Thin auth layout wrapper; delegates to AuthSimpleLayout
- **Props:** title, description
- **Used by:** All auth pages (Login, Register, ForgotPassword, ResetPassword, VerifyEmail, ConfirmPassword)

### resources/js/Layouts/auth/AuthSimpleLayout.vue

- **Purpose:** Centered single-card auth layout; renders college logo image (/images/logo.png, h-20) above the slot
- **Used by:** AuthLayout.vue

### resources/js/Layouts/auth/AuthCardLayout.vue

- **Purpose:** Alternative card-style auth layout
- **Used by:** Not currently active (AuthSimpleLayout is used instead)

### resources/js/Layouts/auth/AuthSplitLayout.vue

- **Purpose:** Split-screen auth layout (content panel + form panel)
- **Used by:** Not currently active

### resources/js/Layouts/settings/Layout.vue

- **Purpose:** Shared layout for settings pages with tab navigation (Profile / Password / Appearance)
- **Used by:** settings/Profile.vue, settings/Password.vue, settings/Appearance.vue

---

## 10. Seeders (database/seeders/)

### database/seeders/DatabaseSeeder.php

Orchestrates all seeders in the correct dependency order. Run with: `php artisan db:seed`

**Run order (updated 2026-08-24, split — `ProjectLifecycleStatusSeeder` added):**
1. RoleSeeder
2. ProjectStatusSeeder
3. ProjectLifecycleStatusSeeder
4. AdminSeeder
5. DummyDataSeeder

### database/seeders/RoleSeeder.php

- **Creates:** 5 Spatie roles via `Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])`
  - super_admin, dept_manager, supervisor, dept_staff, viewer
- **Command:** `php artisan db:seed --class=RoleSeeder`

### database/seeders/ProjectStatusSeeder.php

- **Creates:** exactly 2 project status records (id=1 مؤرشف, id=2 مقترح) via
  `ProjectStatus::updateOrCreate()` using fixed IDs — narrowed from the original 10-status seed by
  the pre-split 2026-08-17 lifecycle scope-down, unchanged in row count/values by the split itself
- Since 2026-08-24, this table backs `proposals.status_id` specifically (the Proposal lifecycle) —
  see Section 2's note distinguishing it from `project_lifecycle_status` below
- See Section 2 (project_status table) for exact values with sort_order and is_active
- **Command:** `php artisan db:seed --class=ProjectStatusSeeder`

### database/seeders/ProjectLifecycleStatusSeeder.php (new, 2026-08-24 split)

- **Creates:** exactly 2 rows in `project_lifecycle_status` (id=1 قيد التنفيذ, id=2 مؤرشف) via
  `DB::table('project_lifecycle_status')->updateOrInsert()` — backs `projects.status_id` (the
  Project lifecycle), independent from `ProjectStatusSeeder` above
- See Section 2 (project_lifecycle_status table) for exact values
- **Command:** `php artisan db:seed --class=ProjectLifecycleStatusSeeder`

### database/seeders/AdminSeeder.php

- **Creates:** 1 admin user
  - email: admin@admin.com
  - name: Admin
  - password: password (hashed via Hash::make)
  - is_active: true
  - role: super_admin (via assignRole)
- **Command:** `php artisan db:seed --class=AdminSeeder`

### database/seeders/DummyDataSeeder.php (rewritten 2026-08-24, split)

Runs everything inside a DB transaction for consistency. Now creates **Proposals** first, then
instantiates a subset of them into **Projects** via `createProposalsAndProjects()` (renamed from
the old `createProjects()`), rather than creating flat `Project` rows directly.

- **Departments (3):**
  - Software Engineering (code: SW)
  - Networks Engineering (code: NET)
  - Electronics Engineering (code: ELEC)

- **Specializations (10 total):**
  - SW: Web Development, Mobile Development, AI & Machine Learning, Database Systems
  - NET: Network Security, Cloud Computing, Wireless Networks
  - ELEC: Embedded Systems, Digital Circuits, Power Electronics

- **Users created:**
  - 3 dept_managers (one per dept): manager.sw@college.com, manager.net@college.com, manager.elec@college.com
  - 6 supervisors (2 per dept): supervisor1.sw@college.com, supervisor2.sw@college.com, supervisor1.net@college.com, supervisor2.net@college.com, supervisor1.elec@college.com, supervisor2.elec@college.com
  - 5 dept_staff (mixed depts): staff1@college.com through staff5@college.com
  - All demo user passwords: password

- **Examiners (8 total):**
  - SW: 3 (Dr./Prof. titles, Arabic names)
  - NET: 3 (Dr./Prof. titles, Arabic names)
  - ELEC: 2 (Dr./Prof. titles, Arabic names)

- **Proposals + Projects (`createProposalsAndProjects()`):** 25 `Proposal` rows created (2-4
  students each via `ProposalStudent`); 21 of them are seeded مؤرشف (`status=1` in the method's
  internal `$projectDefs` list) and immediately get `$proposal->instantiateProject($manager)`
  called on them, producing 21 `Project` rows; the remaining 4 stay مقترح (`status=2`) with no
  linked `Project` at all. Of the 21 instantiated projects, those with a non-null `score` in
  `$projectDefs` (most of them) get 2 assigned examiners (rotated through department examiner
  pairs) + 2 `Evaluation` rows, then `final_score` set and `status_id` flipped to
  `Project::STATUS_ARCHIVED`; every instantiated project also gets its `visit_count` set from
  `$projectDefs`. 3 proposals have `based_on_project_id` set, pointing at a previously-instantiated
  `Project`'s id (evolution chains — decision: `based_on_project_id` always points at `projects.id`,
  never another proposal's id).

- **Command:** `php artisan db:seed --class=DummyDataSeeder`

---

## 11. Tests (tests/)

### tests/Pest.php

Configures Pest to use RefreshDatabase for Feature tests. Defines global helper `userWithRole(string $role): User`.

### tests/TestCase.php

Base test case extending Laravel's base TestCase.

### tests/Unit/ExampleTest.php

- 1 test — basic assertion placeholder
- **Run:** `php artisan test tests/Unit/ExampleTest.php`

### tests/Feature/ExampleTest.php

- 1 test — GET / returns 200
- **Run:** `php artisan test tests/Feature/ExampleTest.php`

### tests/Feature/Auth/AuthenticationTest.php

- Tests login flow and logout
- ~4 test methods

### tests/Feature/Auth/LoginTest.php

- Tests successful login redirects to dashboard
- ~2 test methods

### tests/Feature/Auth/RegistrationTest.php

- Updated to assert 302 redirect to /login (registration is disabled)
- ~2 test methods

### tests/Feature/Auth/EmailVerificationTest.php

- Tests email verification prompt and resend notification
- ~3 test methods

### tests/Feature/Auth/PasswordConfirmationTest.php

- Tests password confirmation screen
- ~3 test methods

### tests/Feature/Auth/PasswordResetTest.php

- Tests password reset request and new password flow
- ~4 test methods

### tests/Feature/Auth/RBACTest.php

- **Purpose:** Tests role-based access control for key routes
- **9 test methods** (updated 2026-08-24, split):
  - Unauthenticated user redirected to /login from /dashboard
  - super_admin can access dashboard
  - super_admin can access admin panel (/admin)
  - dept_staff cannot access admin panel (403)
  - dept_manager cannot access admin panel (403)
  - dept_manager can access /departments
  - viewer cannot access /departments (403)
  - dept_staff can access `route('proposals.create')` (renamed from `/projects/create`, which no
    longer exists — the test file's own comment notes that hitting the literal old URL now matches
    the thin `GET projects/{id}` route with `$id = 'create'` and errors, since project creation
    lives on proposals now)
  - viewer cannot access `route('proposals.create')` (403)
- **Run:** `php artisan test tests/Feature/Auth/RBACTest.php`

### tests/Feature/Roles/RoleVerificationTest.php

- **Purpose:** Comprehensive RBAC verification across all 5 roles — internally rewritten to drive
  `Proposal` (confirmed via source: 8 `Proposal::` references, 0 `Project::` references), same test
  count/shape as before the split
- **58 test methods, 80 assertions:**
  - super_admin (15 tests): full system access, can delete empty dept, blocked on dept with proposals
  - dept_manager (15 tests): cannot create/delete depts, can update own dept, blocked on others dept, proposal lifecycle, examiners, score, cannot import
  - supervisor (11 tests): read-only on projects/dashboard, all write operations return 403
  - dept_staff (13 tests): create/edit pending proposals in own dept, blocked cross-dept, blocked post-instantiate
  - viewer (6 tests): dashboard only, all manage/report/write routes return 403
- **Run:** `php artisan test tests/Feature/Roles/RoleVerificationTest.php`

### tests/Feature/DashboardTest.php

- Tests dashboard accessibility for authenticated users (~2 tests)

### tests/Feature/Models/UserModelTest.php

- Tests User model: fillable fields, casts, relationships (~4 tests)

### tests/Feature/Models/ProjectModelTest.php (narrowed 2026-08-24, split)

- **1 test:** `project supervisor and students are read by reference through the proposal` —
  instantiates a Proposal into a Project, eager-loads `proposal.supervisor`/`proposal.students`,
  and asserts the `$appends` accessors proxy the values correctly. The old fillable/relationship
  assertions for the flat model no longer apply — `Project`'s own fillable/relationships are now
  covered inline by other files (InstantiateProjectTest, ProjectTest) rather than a dedicated
  fillable-fields test.
- **Run:** `php artisan test tests/Feature/Models/ProjectModelTest.php`

### tests/Feature/Models/ProposalModelTest.php (new, 2026-08-24 split)

- **Purpose:** `Proposal`'s permission methods and `instantiateProject()`
- **7 test methods:**
  - `canBeModifiedBy()` true for dept_manager of the same department while مقترح
  - `canBeModifiedBy()` false once مؤرشف, even for the creator
  - super_admin can modify regardless of status or department
  - `canBeInstantiatedBy()` mirrors `canBeArchivedBy()`: dept_manager of dept, مقترح only
  - `instantiateProject()` creates exactly one Project row and flips the proposal's status
  - `instantiateProject()` rolls back the status flip if Project creation fails (forces a
    unique-constraint collision on `proposal_id` to prove the `DB::transaction()` is atomic)
  - A مؤرشف proposal cannot be instantiated again
- **Run:** `php artisan test tests/Feature/Models/ProposalModelTest.php`

### tests/Feature/Seeders/RoleSeederTest.php

- Verifies all 5 roles exist after seeding

### tests/Feature/Seeders/ProjectStatusSeederTest.php

- Verifies exactly 2 project statuses exist after seeding (updated to match the 2-row seed —
  the "10 project statuses" this section previously stated was already stale before the split,
  left over from the pre-2026-08-17 scope-down; fixed here as an incidental accuracy correction)
- **3 test methods:** exactly 2 statuses exist; مؤرشف is id 1 and active; مقترح is id 2 and active
- **Run:** `php artisan test tests/Feature/Seeders/ProjectStatusSeederTest.php`

### tests/Feature/Seeders/ProjectLifecycleStatusSeederTest.php (new, 2026-08-24 split)

- **2 test methods:** exactly 2 project lifecycle statuses exist; قيد التنفيذ is id 1 and مؤرشف is id 2
- **Run:** `php artisan test tests/Feature/Seeders/ProjectLifecycleStatusSeederTest.php`

### tests/Feature/Department/DepartmentTest.php

- **Purpose:** Department CRUD, access control, validation, specialization management
- **14 test methods** covering super_admin full CRUD, dept_manager read/update own dept only, dept_staff blocked, spec create/update/destroy, proposal-link guard on delete (the link-guard fixture now creates a `Proposal`, not a `Project`)
- **Run:** `php artisan test tests/Feature/Department/DepartmentTest.php`

### tests/Feature/Project/ProjectTest.php (narrowed 2026-08-24, split)

- **Purpose:** Under the split, `ProjectController` is thin — index/show only. All the old create/
  replace/delete/archive/search-filter assertions that used to live here moved: create/replace/
  delete ownership matrix → `Proposal/ProposalTest.php`; instantiate (the old "archive" action) →
  `Project/InstantiateProjectTest.php`; examiner/evaluation/final-score → `Examiner/ExaminerTest.php`;
  search/filter → `Search/SearchTest.php` (now against `proposals.index`). What survives here is
  what's still true about the thin Project surface itself.
- **3 test methods:**
  - super_admin can view all projects (via `makeThinProject()` helper: creates a مؤرشف Proposal,
    instantiates it)
  - Project visit count increments on each `projects.show` call
  - `projects.index` never renders a status other than قيد التنفيذ or مؤرشف
- **Run:** `php artisan test tests/Feature/Project/ProjectTest.php`

### tests/Feature/Proposal/ProposalTest.php (new, 2026-08-24 split)

- **Purpose:** Proposal create + the full replace/delete ownership matrix — ported from the
  pre-split `ProjectTest.php`'s ownership assertions, now applied to `Proposal`
- **10 test methods:**
  - dept_staff can create a proposal in their own department
  - Creation never auto-archives for any role (only `instantiate()` ever flips مقترح→مؤرشف — a
    behavior change from the old conflated model, where a manager's create went straight to مؤرشف)
  - Creator can replace their own pending proposal details
  - Non-creator dept_staff cannot replace another staff member's pending proposal
  - dept_manager cannot replace an archived (مؤرشف) proposal
  - super_admin can replace an archived (مؤرشف) proposal
  - dept_manager cannot move a proposal to a different department via replace (403)
  - super_admin can move a proposal to a different department via replace
  - Creator can delete their own pending proposal
  - dept_manager cannot delete an archived (مؤرشف) proposal
- **Run:** `php artisan test tests/Feature/Proposal/ProposalTest.php`

### tests/Feature/Project/InstantiateProjectTest.php (new, 2026-08-24 split)

- **Purpose:** `proposals.instantiate` — the old "archive" action, now the atomic instantiate step
- **5 test methods:**
  - dept_manager of the same department can instantiate and gets redirected to the project show page
  - dept_manager of a different department gets 403
  - super_admin can instantiate regardless of department
  - An already-مؤرشف proposal cannot be instantiated again (double-click guard)
  - dept_staff cannot instantiate
- **Run:** `php artisan test tests/Feature/Project/InstantiateProjectTest.php`

### tests/Feature/Project/ProjectExploitPreventionTest.php (new, 2026-08-24 split)

- **Purpose:** Proves the structural fix found in the pre-migration audit — examiners/evaluations
  can only ever attach to an instantiated `Project`, never reachable via a proposal-only id, since
  `projects`/`proposals` are now separate tables with independent auto-increment sequences
- **2 test methods:**
  - Assigning an examiner to a never-instantiated proposal's id 404s (no project row exists there)
  - `evaluations` table has no row whose `project_id` fails to resolve to an instantiated project
- **Run:** `php artisan test tests/Feature/Project/ProjectExploitPreventionTest.php`

### tests/Feature/Console/MigrateProposalProjectDataTest.php (new, 2026-08-24 split)

- **Purpose:** `php artisan proposals:migrate-legacy-data` (`app/Console/Commands/MigrateProposalProjectData.php`) — the one-time command that migrated the pre-split flat `projects` table into `proposals` + `projects`
- **5 test methods** (against a seeded `projects_legacy` fixture table):
  - Migrates a graded مؤرشف row into a proposal plus an instantiated, graded project (with examiners/evaluations carried over)
  - Migrates an ungraded مؤرشف row into a قيد التنفيذ project with no examiners
  - A مقترح row with no examiners stays proposal-only
  - Cleans erroneous grading data from a مقترح row before migrating it (the exact id=1 data-integrity bug found in the pre-migration audit — see CLAUDE.md)
  - Re-points `based_on_project_id` chains at the new project ids
- **Run:** `php artisan test tests/Feature/Console/MigrateProposalProjectDataTest.php`

### tests/Feature/Search/SearchTest.php

- **Purpose:** SearchService and SearchController functionality — now exercises `Proposal` directly (`Proposal::factory()` fixtures throughout, confirmed via source)
- **15 test methods, 150 assertions** covering search by title, description, case insensitivity, all individual filters, combined filters, empty search, pagination, similarity detection (finds matches, ignores current proposal on edit), suggestions max 5, suggestions match only, unauthenticated blocked
- **Run:** `php artisan test tests/Feature/Search/SearchTest.php`

### tests/Feature/Examiner/ExaminerTest.php

- **Purpose:** Examiner management, assignment, evaluation notes, final score
- **13 test methods, 42 assertions:**
  - dept_manager can create and filter examiners
  - dept_staff blocked from examiner management (403)
  - Validation: required fields
  - Assign examiner to project; max-2 cap enforced; duplicate guard; remove examiner
  - Store evaluation notes
  - dept_manager can set final score; non-manager blocked; score range validated
  - Cannot delete examiner linked to project
- **Run:** `php artisan test tests/Feature/Examiner/ExaminerTest.php`

### tests/Feature/Import/ImportTest.php

- **Purpose:** Bulk import controller and import logic — fixture-building now goes through
  `app/Imports/ProjectsImport.php`'s rewritten row logic (creates a `Proposal`, then
  `instantiateProject()`s it — see Section 4's ImportController entry and Section 6). Note:
  `uploadPdfs()` (the ZIP-matching step) is **not** exercised by this file at all — see the
  accuracy flag on `ImportController::uploadPdfs()` in Section 4 for a latent bug this leaves
  uncovered.
- **15 test methods, 52 assertions:**
  - Access: super_admin allowed, dept_manager gets 403
  - Template download returns XLSX
  - Non-excel file rejected; file > 5MB rejected
  - Valid Excel row creates a proposal + instantiated project with archived status
  - Per-row isolation: bad dept_code/spec/supervisor/missing title fails only that row
  - Students created correctly; empty student slots skipped
  - Summary counts are correct
  - dryRun:true touches 0 DB rows; dryRun:false writes 1 proposal + 1 project
- **Note:** 10 failures occur in XAMPP environment (ext-zip disabled)
- **Run:** `php artisan test tests/Feature/Import/ImportTest.php`

### tests/Feature/Report/ReportTest.php

- **Purpose:** ReportController and ReportService — fixture-building now goes through
  `Proposal::factory()->create()->instantiateProject($actor)` rather than `Project::factory()`
  directly (see `makeReportProject()`/`makeReportProjects()` helpers in the test file)
- **13 test methods, 138 assertions:**
  - Dashboard stats by role (super_admin, dept_manager, dept_staff)
  - dept_manager forced to own dept in departmentReport
  - super_admin can filter departmentReport by dept
  - Specialization ordering correct
  - Supervisor avg_score calculated correctly
  - Yearly growth % calculated
  - PDF export returns download (correct content-type)
  - Excel export returns download
  - Unauthenticated redirect
- **Run:** `php artisan test tests/Feature/Report/ReportTest.php`

### tests/Feature/Admin/UserManagementTest.php

- **Purpose:** Admin user management CRUD
- **9 test methods, 47 assertions:**
  - super_admin can view users list
  - super_admin can filter by role
  - super_admin can create user with role
  - super_admin can update user info and syncRoles
  - super_admin can toggle user active status
  - super_admin cannot delete last super_admin (error flash, user preserved)
  - super_admin can delete non-admin user
  - dept_manager gets 403 on admin users
  - Unauthenticated redirected to /login
- **Run:** `php artisan test tests/Feature/Admin/UserManagementTest.php`

### tests/Feature/Public/PublicBrowseTest.php

- **Purpose:** Public browse and show pages, auth redirect tests — fixtures now build a Proposal
  then instantiate it into an archived Project (the entity these public pages actually query)
- **14 test methods:**
  - Landing page (/) loads
  - Browse page (/browse) loads
  - Search filter returns matching project
  - Dept and year filters work
  - Show archived project returns 200
  - Show non-archived project returns 404
  - Show deleted project returns 404
  - Visit count increments on show
  - /dashboard blocked for guests (redirects to login)
  - /projects blocked for guests
  - POST /register redirects to /login (registration disabled)
- **Run:** `php artisan test tests/Feature/Public/PublicBrowseTest.php`

### tests/Feature/Settings/ProfileUpdateTest.php

- Profile update validation and success (~5 tests)

### tests/Feature/Settings/PasswordUpdateTest.php

- Password update flow (~2 tests)

**Total test suite (as of 2026-08-24): 232 passing / 232 total, 823 assertions, 0 failures.**
This figure is a point-in-time snapshot per the Proposal/Project split's own completion report
(`.superpowers/sdd/2026-08-24-proposal-project-split/`) — re-run the suite for the current count
rather than treating this number as permanent. The previously-noted 10 Import/ext-zip failures
(XAMPP-specific, `ext-zip` disabled) are not reproducing as of this writing; see Section 1's Major
Changes / Bugfixes entries for the change history that led here.

**Run all tests:** `php artisan test`

---

## 12. Implemented Features (Phase by Phase)

### Phase A: Models + Seeders

- 10 Eloquent models with fillable, casts, relationships
- 15 database migrations (users, departments, specializations, project_status, examiners, projects, project_students, project_documents, project_examiners, evaluations, + Spatie permission tables)
- RoleSeeder: 5 roles
- ProjectStatusSeeder: 10 statuses
- AdminSeeder: admin@admin.com / password / super_admin
- DummyDataSeeder: 3 depts, 10 specs, 14 users, 8 examiners, 25 projects with students/evaluations/examiner assignments
- HasFactory on Department, Specialization, Project, Examiner
- Factories: DepartmentFactory, SpecializationFactory, ProjectFactory, ExaminerFactory, UserFactory updated with is_active

### Phase B: RBAC Middleware

- RoleMiddleware class registered as 'role' alias in bootstrap/app.php
- All route groups use `middleware(['auth', 'role:...'])`
- HandleInertiaRequests shares user role via getRoleNames()->first()
- types/index.ts SharedData type extended with role on auth.user
- Registration disabled (POST /register redirects to /login with error flash)

### Phase C: Department and Specialization Management

- DepartmentController: full resource (index/create/store/edit/update/destroy)
- SpecializationController: store/update/destroy
- 4 Form Request classes
- Department ownership enforcement: dept_manager can only edit own dept
- Project-link guard on delete for both dept and spec
- Departments/Index.vue with expandable rows and role-based buttons
- Departments/Create.vue and Departments/Edit.vue
- Modal.vue and ConfirmDelete.vue shared components

### Phase D: Project Management

- StoreProjectRequest and UpdateProjectRequest with PDF validation (mime:pdf, max 15MB)
- ProjectController: 8 methods
- Status auto-assignment: dept_manager/super_admin -> archived; dept_staff -> proposal_submitted
- archive() endpoint changes status to archived — now guarded to dept_manager of that project's department while pending (مقترح), or super_admin (see `Project::canBeArchivedBy()`)
- Soft delete via is_deleted=true (not deleted_at)
- PDF stored in storage/public/projects; ProjectDocument record created on upload (historical —
  this was the original `ProjectController`, since fully rebuilt by the 2026-08-24 split; the
  current `ProposalController` stores the PDF path directly on `draft_file_path` and never creates
  a `ProjectDocument` record — see Section 3/4)
- authorizeEdit() private method enforces role/dept/status rules
- Projects/Index.vue: paginated table, status badges, role-based actions, similarity warning
- Projects/Create.vue: dynamic student rows, cascaded dept->spec, PDF upload with progress
- Projects/Edit.vue: pre-filled form, current PDF display, replace PDF
- Projects/Show.vue: 2-column layout, students, examiners, score sidebar

### Phase E: Search + Filter + Similarity

- SearchService: searchProjects, detectSimilarity, getFilterOptions
- ProjectController constructor-injects SearchService
- SearchController: index -> Search/Index.vue; suggestions -> JSON
- similarity_warning flash shared globally
- SearchBar.vue, FilterPanel.vue, SimilarityWarning.vue components
- Projects/Index.vue rebuilt with active filter chips
- Search/Index.vue with grouped results and highlighted match text

### Phase F: Examiners + Evaluations + Final Score

- ExaminerController: index/store/update/destroy with dept filter and project-link guard
- ProjectExaminerController: assign/remove with max-2 cap and duplicate guard
- EvaluationController: store notes, updateScore
- 5 Form Request classes
- ExaminerFactory created
- Examiners/Index.vue: add/edit modal, delete confirm, dept filter
- AssignExaminerModal.vue, ScoreInput.vue components
- Projects/Show.vue: assign/remove examiner UI, per-examiner evaluation notes, ScoreInput

### Phase G: Bulk Import

- maatwebsite/excel package installed
- ProjectImportTemplate: 13-column template with styled header row
- ProjectsImport: validates fields, finds FK deps, creates Project+students, dryRun flag, getSummary()
- ImportController: 5 methods including ZIP PDF upload and matching
- Import/Index.vue: 4-step wizard UI

### Phase H: Reports + Dashboard

- ReportService: 5 methods for all statistical data
- ReportExport class for Excel with headings, title, styles
- PDF Blade view (resources/views/exports/report.blade.php) — RTL Arabic compatible
- ReportController: 7 methods
- StatsCard.vue, ExportButtons.vue components
- Dashboard.vue: fully role-based layout
- Reports/Department.vue, Reports/Specializations.vue, Reports/Supervisors.vue, Reports/Yearly.vue

### Phase I: User Management

- Admin/UserController: index/store/update/toggleActive/destroy
- StoreUserRequest, UpdateUserRequest
- Cannot delete last super_admin guard
- Admin/Users/Index.vue: full management UI with color-coded role badges

### Public Browse

- PublicController: index (landing stats), browse (filtered archive), show (detail + related)
- All 3 routes outside auth middleware
- Public/Browse.vue: no-auth layout, 3-column project grid, search+filters, pagination
- Public/Show.vue: full project detail, related projects section

### UI/UX

- Tailwind custom brand palette (primary #103A52 dark blue, #1B6B93 medium blue, #4FA8C9 light blue)
- Arabic RTL layout: html lang="ar" dir="rtl" in app.blade.php
- Tajawal and IBM Plex Sans Arabic via Google Fonts
- Sidebar right-anchored for RTL; RTL sidebar gap fixed (removed flex-row-reverse)
- College logo in auth layout and public pages (/images/logo.png)
- All UI text in Arabic
- Dark mode supported throughout (Tailwind dark: prefix)
- DesignSystem.vue dev reference page at /design-system

---

## 13. Pending Features (Not Implemented)

### Phase 2 Features (from CLAUDE.md)

1. **Student eligibility system** — `student_eligibility` table not yet created; no UI for checking eligibility before project registration
2. **Full 11-stage project lifecycle** — 10 statuses exist in DB but only 2 are actively used (proposal_submitted and archived); the other 8 (supervisor_approved, hod_approved, in_progress, ready_for_defense, under_defense, revisions_required, rejected, cancelled) have no UI transitions or business logic
3. **Supervisor approval gates** — supervisor role is currently read-only; has no action routes of any kind
4. **Defense scheduling** — no `defense` table; no scheduling UI
5. **Document milestone tracking** — the `project_documents` table and `ProjectDocument` model were
   dropped entirely by the 2026-08-24 proposal/project split (confirmed empty, 0 rows, at drop
   time); no milestone document tracking exists in any form today, not even the original
   upload-only version — a PDF is now just `draft_file_path` on `Proposal`, with no separate
   document history record at all
6. **Status history logging** — no `status_history` table; no audit trail of status changes over time

### Additional Gaps Observed

7. **viewer role** — functions identically to a basic authenticated user; no special viewer-only pages or restrictions separate it meaningfully from supervisor beyond test assertions
8. **Registration** — completely disabled. No admin invite flow exists; new users can only be created by super_admin via /admin/users
9. **is_active enforcement** — the is_active flag is stored and toggled in the UI, but there is no middleware or auth guard that blocks login for inactive users
10. **/design-system route** — accessible with no auth in production; must be removed before deployment
11. **Similarity detection algorithm** — uses simple SQL LIKE query; no semantic NLP-based similarity
12. **based_on_project_id** — self-referential evolution link seeded with 3 examples; no UI to set or view it
13. **File upload security** — PDF validation relies on MIME type checking only; no server-side content inspection

---

## 14. Known Issues and TODOs

### From CLAUDE.md Status Notes

- **ext-zip disabled in XAMPP** — The ZIP upload feature (ImportController::uploadPdfs) requires the `zip` PHP extension. In XAMPP this is typically disabled by default. Enable it by uncommenting `extension=zip` in php.ini and restarting Apache. This causes 10 test failures in the Import test suite.
- **/design-system route with no auth** — Must be removed from routes/web.php before deploying to production.

### Code-level Issues

- **DashboardController is unused** — `app/Http/Controllers/DashboardController.php` exists but the `/dashboard` route points to `ReportController::dashboard()`. DashboardController contains slightly different stat logic (includes total_users; lacks pending_approvals). This file can be deleted or the routes reconciled.
- **final_score type mismatch** — Cast as `decimal:2` on the model but Vue type definitions show `string | null`. The pass/fail threshold (50) is hardcoded in both `ScoreInput.vue` and `Projects/Show.vue` — consider extracting to a constant.
- **visit_count has no deduplication** — Every call to ProjectController::show() and PublicController::show() increments the counter, including calls by the project owner and managers.
- **is_active on specializations** — The column exists on the specializations table (default true) but no filter in the application restricts inactive specializations from appearing in dropdowns.
- **Arabic text in PHP controllers** — Flash messages in controllers use Arabic strings (e.g., "تم إنشاء القسم بنجاح"). This mixes display language into backend logic; consider using lang files for maintainability.

### CI / Test Environment

- **CI's `Inertia::ensurePagesExist` checks fail on the Linux runner** due to a case/path mismatch with `resources/js/pages` — pre-existing since ~2026-07-03, environmental not code-level. Every `->component()` test assertion is affected on CI; all pass locally. Deferred to a dedicated CI-hardening branch.

---

## 15. How to Run the Project

### Requirements

- PHP 8.2 or higher
- Required PHP extensions: pdo_mysql, mbstring, openssl, fileinfo, gd, xml, zip
- Composer 2.x
- Node.js 18+ and npm
- MariaDB 10.4 (or MySQL 8.0 compatible)

### Installation Steps

```bash
# 1. Navigate to project directory
cd graduation-archive

# 2. Install PHP dependencies
composer install

# 3. Install Node.js dependencies
npm install

# 4. Copy environment file
cp .env.example .env

# 5. Generate application key
php artisan key:generate
```

### .env Setup

Open `.env` and set:

```
APP_NAME="Graduation Archive"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=graduation_archive
DB_USERNAME=root
DB_PASSWORD=
```

### Database Setup

```sql
-- In MariaDB/MySQL client, create the database:
CREATE DATABASE graduation_archive
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

```bash
# Run all migrations
php artisan migrate

# Seed all reference data + demo data
php artisan db:seed

# Or seed incrementally:
php artisan db:seed --class=RoleSeeder
php artisan db:seed --class=ProjectStatusSeeder
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=DummyDataSeeder
```

### Create Storage Symlink

```bash
php artisan storage:link
```

### Running the Development Server

```bash
# Terminal 1: Laravel backend server
php artisan serve

# Terminal 2: Vite frontend dev server
npm run dev
```

Or use the combined Composer script (runs all three: serve + queue + vite):

```bash
composer run dev
```

App will be accessible at: http://localhost:8000

### Running Tests

```bash
# Run entire test suite
php artisan test

# Run a specific test file
php artisan test tests/Feature/Project/ProjectTest.php

# Run with test coverage report
php artisan test --coverage

# Run tests matching a name
php artisan test --filter "dept_manager can create project"
```

### Default Login Credentials

| Role | Email | Password |
|---|---|---|
| super_admin | admin@admin.com | password |
| dept_manager (SW) | manager.sw@college.com | password |
| dept_manager (NET) | manager.net@college.com | password |
| dept_manager (ELEC) | manager.elec@college.com | password |
| supervisor (SW, #1) | supervisor1.sw@college.com | password |
| supervisor (NET, #1) | supervisor1.net@college.com | password |
| dept_staff (#1) | staff1@college.com | password |

### Fixing ext-zip for Import Feature (XAMPP)

Open `php.ini` (in XAMPP: C:\xampp\php\php.ini), find and uncomment:
```
extension=zip
```
Then restart Apache via XAMPP Control Panel.

---

## 16. File Structure

```
graduation-archive/
|
|-- app/
|   |-- Http/
|   |   |-- Controllers/
|   |   |   |-- Controller.php
|   |   |   |-- DashboardController.php
|   |   |   |-- DepartmentController.php
|   |   |   |-- EvaluationController.php
|   |   |   |-- ExaminerController.php
|   |   |   |-- ImportController.php
|   |   |   |-- ProjectController.php
|   |   |   |-- ProjectExaminerController.php
|   |   |   |-- PublicController.php
|   |   |   |-- ReportController.php
|   |   |   |-- SearchController.php
|   |   |   |-- SpecializationController.php
|   |   |   |-- Admin/
|   |   |   |   |-- UserController.php
|   |   |   |-- Auth/
|   |   |   |   |-- AuthenticatedSessionController.php
|   |   |   |   |-- ConfirmablePasswordController.php
|   |   |   |   |-- EmailVerificationNotificationController.php
|   |   |   |   |-- EmailVerificationPromptController.php
|   |   |   |   |-- NewPasswordController.php
|   |   |   |   |-- PasswordResetLinkController.php
|   |   |   |   |-- RegisteredUserController.php
|   |   |   |   |-- VerifyEmailController.php
|   |   |   |-- Settings/
|   |   |       |-- PasswordController.php
|   |   |       |-- ProfileController.php
|   |   |-- Middleware/
|   |   |   |-- RoleMiddleware.php
|   |   |-- Requests/
|   |       |-- AssignExaminerRequest.php
|   |       |-- StoreDepartmentRequest.php
|   |       |-- StoreEvaluationRequest.php
|   |       |-- StoreExaminerRequest.php
|   |       |-- StoreProjectRequest.php
|   |       |-- StoreUserRequest.php
|   |       |-- StoreSpecializationRequest.php
|   |       |-- UpdateDepartmentRequest.php
|   |       |-- UpdateExaminerRequest.php
|   |       |-- UpdateProjectRequest.php
|   |       |-- UpdateScoreRequest.php
|   |       |-- UpdateUserRequest.php
|   |       |-- UpdateSpecializationRequest.php
|   |-- Models/
|   |   |-- Department.php
|   |   |-- Evaluation.php
|   |   |-- Examiner.php
|   |   |-- Project.php
|   |   |-- ProjectExaminer.php
|   |   |-- ProjectLifecycleStatus.php
|   |   |-- ProjectStatus.php
|   |   |-- Proposal.php
|   |   |-- ProposalStudent.php
|   |   |-- Specialization.php
|   |   |-- User.php
|   |-- Services/
|   |   |-- ReportService.php
|   |   |-- SearchService.php
|   |-- Exports/
|   |   |-- ProjectImportTemplate.php
|   |   |-- ReportExport.php
|   |-- Imports/
|       |-- ProjectsImport.php
|
|-- resources/
|   |-- js/
|   |   |-- pages/
|   |   |   |-- Welcome.vue
|   |   |   |-- Dashboard.vue
|   |   |   |-- DesignSystem.vue
|   |   |   |-- auth/
|   |   |   |   |-- Login.vue
|   |   |   |   |-- Register.vue
|   |   |   |   |-- ForgotPassword.vue
|   |   |   |   |-- ResetPassword.vue
|   |   |   |   |-- VerifyEmail.vue
|   |   |   |   |-- ConfirmPassword.vue
|   |   |   |-- settings/
|   |   |   |   |-- Profile.vue
|   |   |   |   |-- Password.vue
|   |   |   |   |-- Appearance.vue
|   |   |   |-- Admin/
|   |   |   |   |-- Users/
|   |   |   |       |-- Index.vue
|   |   |   |-- Departments/
|   |   |   |   |-- Index.vue
|   |   |   |   |-- Create.vue
|   |   |   |   |-- Edit.vue
|   |   |   |-- Examiners/
|   |   |   |   |-- Index.vue
|   |   |   |-- Import/
|   |   |   |   |-- Index.vue
|   |   |   |-- Projects/
|   |   |   |   |-- Index.vue
|   |   |   |   |-- Create.vue
|   |   |   |   |-- Edit.vue
|   |   |   |   |-- Show.vue
|   |   |   |-- Public/
|   |   |   |   |-- Browse.vue
|   |   |   |   |-- Show.vue
|   |   |   |-- Reports/
|   |   |   |   |-- Department.vue
|   |   |   |   |-- Specializations.vue
|   |   |   |   |-- Supervisors.vue
|   |   |   |   |-- Yearly.vue
|   |   |   |-- Search/
|   |   |       |-- Index.vue
|   |   |-- components/
|   |   |   |-- Brand/
|   |   |   |   |-- Logo.vue
|   |   |   |-- AppContent.vue
|   |   |   |-- AppHeader.vue
|   |   |   |-- AppLogo.vue
|   |   |   |-- AppLogoIcon.vue
|   |   |   |-- AppShell.vue
|   |   |   |-- AppSidebar.vue
|   |   |   |-- AppSidebarHeader.vue
|   |   |   |-- AppearanceTabs.vue
|   |   |   |-- AssignExaminerModal.vue
|   |   |   |-- ConfirmDelete.vue
|   |   |   |-- DataTable.vue
|   |   |   |-- DeleteUser.vue
|   |   |   |-- ExportButtons.vue
|   |   |   |-- FilterPanel.vue
|   |   |   |-- Heading.vue
|   |   |   |-- HeadingSmall.vue
|   |   |   |-- Icon.vue
|   |   |   |-- InputError.vue
|   |   |   |-- Modal.vue
|   |   |   |-- NavFooter.vue
|   |   |   |-- NavMain.vue
|   |   |   |-- NavUser.vue
|   |   |   |-- PlaceholderPattern.vue
|   |   |   |-- ScoreInput.vue
|   |   |   |-- SearchBar.vue
|   |   |   |-- SimilarityWarning.vue
|   |   |   |-- StatsCard.vue
|   |   |   |-- TextLink.vue
|   |   |   |-- UserInfo.vue
|   |   |   |-- UserMenuContent.vue
|   |   |   |-- ui/  (shadcn/radix-vue primitive components)
|   |   |       |-- avatar/  (3 components)
|   |   |       |-- breadcrumb/  (7 components)
|   |   |       |-- button/  (1 component)
|   |   |       |-- card/  (6 components)
|   |   |       |-- checkbox/  (1 component)
|   |   |       |-- collapsible/  (3 components)
|   |   |       |-- dialog/  (10 components)
|   |   |       |-- dropdown-menu/  (14 components)
|   |   |       |-- input/  (1 component)
|   |   |       |-- label/  (1 component)
|   |   |       |-- navigation-menu/  (8 components)
|   |   |       |-- separator/  (1 component)
|   |   |       |-- sheet/  (8 components)
|   |   |       |-- sidebar/  (22 components)
|   |   |       |-- skeleton/  (1 component)
|   |   |       |-- tooltip/  (4 components)
|   |   |-- layouts/
|   |       |-- AppLayout.vue
|   |       |-- AuthLayout.vue
|   |       |-- app/
|   |       |   |-- AppHeaderLayout.vue
|   |       |   |-- AppSidebarLayout.vue
|   |       |-- auth/
|   |       |   |-- AuthCardLayout.vue
|   |       |   |-- AuthSimpleLayout.vue
|   |       |   |-- AuthSplitLayout.vue
|   |       |-- settings/
|   |           |-- Layout.vue
|   |-- css/
|   |   |-- app.css  (Google Fonts @import + Tailwind base)
|   |-- views/
|       |-- app.blade.php  (root template: lang="ar" dir="rtl")
|       |-- exports/
|           |-- report.blade.php  (RTL Arabic PDF export template)
|
|-- database/
|   |-- migrations/
|   |   |-- 0001_01_01_000000_create_users_table.php
|   |   |-- 0001_01_01_000001_create_cache_table.php
|   |   |-- 0001_01_01_000002_create_jobs_table.php
|   |   |-- 2026_06_15_094926_create_permission_tables.php
|   |   |-- 2026_06_15_100000_create_departments_table.php
|   |   |-- 2026_06_15_100001_create_specializations_table.php
|   |   |-- 2026_06_15_100002_create_project_status_table.php
|   |   |-- 2026_06_15_100003_add_fields_to_users_table.php
|   |   |-- 2026_06_15_100004_create_examiners_table.php
|   |   |-- 2026_06_15_100005_create_projects_table.php
|   |   |-- 2026_06_15_100006_create_project_students_table.php
|   |   |-- 2026_06_15_100007_create_project_documents_table.php
|   |   |-- 2026_06_15_100008_create_project_examiners_table.php
|   |   |-- 2026_06_15_100009_create_evaluations_table.php
|   |   |-- 2026_06_17_000001_add_description_to_departments_table.php
|   |-- seeders/
|       |-- DatabaseSeeder.php
|       |-- RoleSeeder.php
|       |-- ProjectStatusSeeder.php
|       |-- AdminSeeder.php
|       |-- DummyDataSeeder.php
|
|-- tests/
|   |-- Pest.php
|   |-- TestCase.php
|   |-- Unit/
|   |   |-- ExampleTest.php
|   |-- Feature/
|       |-- ExampleTest.php
|       |-- DashboardTest.php
|       |-- Admin/
|       |   |-- UserManagementTest.php
|       |-- Auth/
|       |   |-- AuthenticationTest.php
|       |   |-- EmailVerificationTest.php
|       |   |-- LoginTest.php
|       |   |-- PasswordConfirmationTest.php
|       |   |-- PasswordResetTest.php
|       |   |-- RBACTest.php
|       |   |-- RegistrationTest.php
|       |-- Department/
|       |   |-- DepartmentTest.php
|       |-- Examiner/
|       |   |-- ExaminerTest.php
|       |-- Import/
|       |   |-- ImportTest.php
|       |-- Models/
|       |   |-- ProjectModelTest.php
|       |   |-- UserModelTest.php
|       |-- Project/
|       |   |-- ProjectTest.php
|       |-- Public/
|       |   |-- PublicBrowseTest.php
|       |-- Report/
|       |   |-- ReportTest.php
|       |-- Roles/
|       |   |-- RoleVerificationTest.php
|       |-- Search/
|       |   |-- SearchTest.php
|       |-- Seeders/
|       |   |-- ProjectStatusSeederTest.php
|       |   |-- RoleSeederTest.php
|       |-- Settings/
|           |-- PasswordUpdateTest.php
|           |-- ProfileUpdateTest.php
|
|-- routes/
|   |-- web.php
|   |-- auth.php
|   |-- settings.php
|
|-- composer.json
|-- package.json
|-- tailwind.config.js
|-- vite.config.ts
|-- tsconfig.json
|-- CLAUDE.md
|-- PROGRESS.md  (this file)
```
