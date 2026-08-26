# Graduation Archive System — Claude Code Context

## Project Overview
Intelligent graduation project archiving and classification system for the Electronic Technology College.
Built with: Laravel 11 + Vue 3 + Inertia.js + Tailwind CSS + Spatie Permissions + MariaDB

## Tech Stack
- **Backend**: Laravel 11, PHP 8.2
- **Frontend**: Vue 3, Inertia.js 2, Tailwind CSS 3, TypeScript
- **Database**: MariaDB 10.4 (MySQL compatible)
- **Auth**: Laravel Breeze (built-in) + Spatie laravel-permission v6
- **Build**: Vite

## Project Structure
```
app/
  Models/          → Eloquent models (13 tables)
  Http/
    Controllers/   → Request handlers
    Middleware/    → RBAC middleware
  Services/        → Business logic layer
database/
  migrations/      → All 13 table migrations
  seeders/         → Initial data (roles, statuses, admin)
resources/js/
  Pages/           → Inertia Vue pages
  Components/      → Reusable Vue components
  Layouts/         → Page layouts
routes/
  web.php          → All routes
```

## Database — 13 Tables
Phase 1 (Active Now):
1. roles (Spatie) — 5 roles
2. departments
3. specializations
4. users (+ registration_number field)
5. proposals — the paper-approved form (title/description/dept/spec/supervisor/students/PDF);
   2-state lifecycle (مقترح/مؤرشف), never graded
6. proposal_students (weak entity, with status/withdrawal) — belongs to a proposal, not a project
7. projects — the actual in-progress/graded work; `belongsTo` a proposal (unique `proposal_id`,
   `restrictOnDelete`); reads supervisor/students by reference through its proposal, never
   duplicates them
8. examiners
9. project_examiners (M:N junction) — attaches to an instantiated `projects` row only
10. project_status — reference table for `proposals.status_id` (id=1 مؤرشف, id=2 مقترح)
11. project_lifecycle_status — reference table for `projects.status_id` (id=1 قيد التنفيذ,
    id=2 مؤرشف) — a separate table/lifecycle from `project_status` above; do not confuse the two
12. evaluations — attaches to an instantiated `projects` row only

Phase 2 (Future):
13. student_eligibility
14. supervisor_history

`project_documents` was dropped entirely on 2026-08-24 (empty table, 0 rows, confirmed via audit
before dropping) — milestone document tracking is not currently implemented anywhere in the split
schema.

Note: the *proposal* lifecycle is exactly 2 statuses (see "Key Business Rules" below); the
*project* lifecycle is a separate, also-2-status table (see table 11 above). DEFENSE and
STATUS_HISTORY were never implemented — planning-only, now removed from scope entirely.

`supervisor_approved_by/_at` and `department_approved_by/_at` (added 2026-08-17) were removed on
2026-08-23 — approval turned out to happen on paper, outside the system, so the fields tracked
nothing any controller or UI ever read. `proposals.created_by` (FK to users, nullable) carries
forward the creator-or-department-manager permission check described below.

## Roles (RBAC via Spatie)
1. super_admin — full access
2. dept_manager — manage own department
3. supervisor — approval gates only (no data entry)
4. dept_staff — add projects (pending approval)
5. viewer — browse only (Phase 2)

## Key Business Rules
This is a two-entity lifecycle: a **Proposal** (the paper-approved form) is created, then a
dept_manager (or super_admin) **instantiates** it into a **Project** (the actual in-progress/
graded work). A proposal is never graded; a project has no independent supervisor/specialization
fields of its own — it reads them by reference through `project->proposal`.

- One proposal = one specialization, one supervisor; the project it produces inherits both by
  reference, never by copy
- Fixed 2 examiners per project (examiners/evaluations can only ever attach to an instantiated
  `projects` row — there is structurally no project-shaped row at a still-مقترح proposal's id for
  them to attach to)
- Final score entered by dept_manager only, on the `projects` row
- PDF files only, max 15MB (`proposals.draft_file_path`)
- Soft delete on both proposals and projects (`is_deleted` field on each, independently)
- **Proposal lifecycle** is exactly 2 statuses: "مقترح" (pending, id=2) → "مؤرشف" (archived, id=1)
  in `project_status`. IDs kept as originally seeded from the pre-split single-entity model (id=1
  already meant "archived" everywhere in the codebase, so only the seeded Arabic text/sort_order
  changed, not which ID means what).
- **Project lifecycle** is a separate 2-status table (`project_lifecycle_status`): "قيد التنفيذ"
  (in progress, id=1, the default `instantiateProject()` lands a new project at) → "مؤرشف"
  (archived, id=2). The finalize flow now moves a project between these two states:
  `POST /projects/{id}/finalize` (dept_manager/dept_staff of the project's department, or
  super_admin — via `Project::canBeFinalizedBy()`) requires exactly 2 examiners assigned AND a
  final score set AND a PDF upload (validated via `Project::finalizationBlockers()` /
  `FinalizeProjectRequest`), stores the file to `final_file_path`, and moves the project to
  `STATUS_ARCHIVED`. Once archived, `ProjectExaminerController`/`EvaluationController` reject
  further examiner/evaluation/score changes (guard clause, `status_id === STATUS_ARCHIVED`), and
  the project becomes visible on public browse/show with a downloadable final file.
- Supervisor and department approval happen on paper, outside the system — there is no digital
  approval tracking. A proposal created by dept_manager/dept_staff is implicitly pre-approved by
  their role; the system's only job is to gate what can happen to it while it's مقترح (pending) —
  creation itself never auto-archives for any role, including dept_manager (that was the old
  conflated-model behavior; now only `instantiateProject()` ever moves a proposal to مؤرشف):
  - Replace details/file or soft-delete: only the creator (`proposals.created_by`) or the
    dept_manager of that department, and only while status_id = STATUS_PENDING (مقترح).
    super_admin bypasses both the status and ownership checks. Moving a proposal to a different
    department via replace is super_admin-only, even for a dept_manager who otherwise passes the
    ownership check.
  - Instantiate (مقترح → مؤرشف + creates the linked `projects` row): only a dept_manager of that
    same department (or super_admin), only while status_id = STATUS_PENDING — this is the single
    atomic action that used to be called "archive" on the old flat `Project` model. Once مؤرشف, a
    proposal cannot be instantiated again (double-click guard), and replace/delete on the proposal
    are locked for everyone except super_admin. See `Proposal::canBeModifiedBy()`/
    `canBeInstantiatedBy()` (ported from the old `Project::canBeModifiedBy()`/`canBeArchivedBy()`)
    for the single source of truth, and `UpdateProposalRequest`/`DeleteProposalRequest`/
    `InstantiateProjectRequest` for where it's enforced.
- Only milestone documents were ever in scope, and even that scope was dropped along with
  `project_documents` (see "Database" above) — nothing tracks per-document approval today
- dept_staff proposals stay مقترح (pending) until a dept_manager of that department instantiates
  them — see the paper-approval bullet above for the full permission matrix
- Max 10 concurrent users
- Arabic RTL interface

## Phase 1 Features (Implement Now)
- [x] Authentication (done via Breeze)
- [ ] RBAC setup (Spatie roles/permissions)
- [ ] Department & Specialization management
- [ ] Project CRUD with PDF upload
- [ ] Project approval workflow (staff → manager)
- [ ] Search & Filter (title, dept, specialization, year, supervisor)
- [ ] Examiner management + link to project
- [ ] Final score entry
- [ ] Bulk import via Excel
- [ ] Reports & Dashboard
- [ ] Similarity detection warning

## Phase 2 Features (Future)
- Student eligibility system
- Supervisor history tracking

Explicitly out of scope (not planned): the original 11-stage/8-status lifecycle, defense
scheduling, and status history logging. Supervisor and department approval are handled as
fields on `projects`, not as pipeline stages — see "Key Business Rules".

## Coding Conventions
- Controllers: ResourceController pattern
- Models: use HasRoles (Spatie trait)
- Services: one service per module (ProjectService, ReportService...)
- Vue Pages: PascalCase (ProjectIndex.vue, ProjectCreate.vue)
- Components: Reusable in /Components folder
- API responses via Inertia::render() — no separate API
- Form validation: Laravel Form Requests
- File upload: Laravel Storage (public disk)

## Database Naming
- Tables: snake_case plural (projects, project_students)
- Foreign keys: table_singular_id (project_id, department_id)
- Soft delete: is_deleted boolean (not Laravel's deleted_at)
- Timestamps: created_at, updated_at on all tables

## Environment
- Local: XAMPP (MariaDB) on Windows
- DB: graduation_archive
- User: root, Password: (empty)
- Storage: public disk for PDF files
- Max upload: 15MB PDF only

## Current Status
- ✅ **Project Finalize/Archive** — see
  `.superpowers/sdd/2026-08-25-project-finalize-archive/` for the full per-task history. Adds a
  nullable `final_file_path` string column to `projects`; `Project::canBeFinalizedBy()`
  (super_admin always; dept_manager/dept_staff of `proposal->department_id`; false once archived)
  and `Project::finalizationBlockers()` (exactly-2-examiners + score gate) on the model;
  `POST /projects/{id}/finalize` (`FinalizeProjectRequest` validates the uploaded file plus
  readiness via the blockers above). `ProjectExaminerController`/`EvaluationController` now reject
  writes once a project is archived (guard clause added to `assign()`/`remove()`/`store()`/
  `updateScore()`). `Projects/Show.vue` has a new finalize card (orange `ConfirmDelete` confirm
  dialog variant) visible to dept_manager/dept_staff/super_admin while not yet archived, showing
  readiness blockers, then a download link once archived. `Public/Show.vue` now links to
  `project.final_file_path` instead of `project.proposal.draft_file_path`.
- ✅ **Proposal/Project Split — Task 8 final review complete** — 232/232 total suite (0 failures),
  frontend build clean. Docs (PROGRESS.md Sections 3-11) brought up to date with the split. Full
  branch diff (`main...feature/proposal-project-split`) reviewed via `/code-review`; real findings
  fixed:
  - `ProposalController::destroy()` now cascades `is_deleted` to the linked instantiated `Project`
    (in one transaction) — previously, deleting an already-instantiated proposal left its `Project`
    fully visible everywhere (public browse, `/projects`, report stats), silently defeating the
    delete.
  - `ImportController::uploadPdfs()` — was matching/updating against `project_title`/
    `draft_file_path`/`documents()`, none of which exist on the post-split `Project` model (an
    untested, guaranteed-to-crash latent bug); now matches `Proposal::title` and sets
    `draft_file_path` on the proposal directly.
  - `ProjectsImport.php` — bulk-imported rows with no `final_score` were silently left at
    قيد التنفيذ (in progress, invisible on the public site) instead of مؤرشف; now always archives,
    matching the old importer's unconditional behavior for historical work.
  - `ReportController::dashboard()`'s non-super_admin branch counted raw `Project` rows for
    `total_projects` while `ReportService` (super_admin path) counts `Proposal` rows — same stat
    label, two different meanings by role; unified on `Proposal`.
  - `SimilarityWarning.vue` linked to `route('projects.show', p.id)` with a *proposal* id (404 or
    wrong-project risk); fixed to `proposals.show`. The warning itself was also never actually
    reaching the user — `ProposalController::store()`/`update()` both redirect to `proposals.show`,
    but only `Proposals/Index.vue` rendered `<SimilarityWarning>`; added it to `Proposals/Show.vue`
    too.
  - `Proposals/Show.vue`'s instantiate confirm button had no in-flight guard — a double-click could
    fire two `POST .../instantiate` requests, the second throwing an uncaught unique-constraint
    `QueryException` (`projects.proposal_id` is unique); added a client-side submitting-lock.
  - Structural cleanup: `Project::$with` now eager-loads `proposal.supervisor`/`proposal.students`
    by default (the two relations backing its `$appends` accessors) instead of relying on 5 separate
    call sites each remembering the exact literal; dead files `ProjectStudent.php`/
    `ProjectDocument.php` deleted; `ProjectStatus`'s stale `projects()` relation (pointed at a
    dropped column) replaced with a working `proposals()`; duplicate similarity-warning-building
    code in `ProposalController` and duplicate avg-score-by-column query in `ReportService` each
    extracted to a private helper.
  - Deliberately **not** changed, with reasoning: the 3 legacy rows that migrated to قيد التنفيذ
    despite having been مؤرشف pre-split (ids 3, 11, 17 — zero examiners at migration time) — this
    was flagged as a disagreement with one review finding, since it's the plan's own documented,
    deliberate judgment call (see the "Global Constraints" note in
    `docs/superpowers/plans/2026-08-24-proposal-project-split.md`), not an oversight; and batching
    `ProjectsImport`'s per-row `instantiateProject()` transactions into one outer transaction was
    rejected — it would change bulk import from partial-success-per-row to all-or-nothing on any
    single row's failure, a real behavior regression, not a pure efficiency win.
- ✅ **Proposal/Project Split** — 232/232 total suite (0 failures)
  - The previously-conflated `Project` entity is now two entities: **`Proposal`** (the
    paper-approved form — title/description/dept/spec/supervisor/students/PDF, 2-state lifecycle
    مقترح↔مؤرشف via `project_status`, never graded) and **`Project`** (the actual in-progress/
    graded work — `belongsTo` a `Proposal` via a unique, `restrictOnDelete` `proposal_id`; reads
    `supervisor`/`students` by reference through `$project->proposal` via `$appends =
    ['supervisor', 'students']` accessors, never duplicating them onto `projects`). See "Key
    Business Rules" above for the full lifecycle/permission matrix.
  - `Proposal::instantiateProject(User $actor): Project` — the single atomic action (wrapped in
    `DB::transaction()`) that flips a proposal مقترح→مؤرشف and creates its linked `Project` row in
    one step; either both happen or neither does (verified by a test that force-fails the insert
    and confirms the status flip rolls back). Gated by `Proposal::canBeInstantiatedBy(User $user)`
    — ported from the old flat `Project::canBeArchivedBy()`: true for super_admin unconditionally,
    otherwise requires dept_manager of that proposal's department AND status_id = STATUS_PENDING.
    Enforced via `InstantiateProjectRequest` on `POST proposals/{id}/instantiate`.
  - Data migration: `app/Console/Commands/MigrateProposalProjectData.php`, run via
    `php artisan proposals:migrate-legacy-data`. Before migrating, it cleans up a data-integrity
    bug found during the pre-migration audit — legacy project id=1 was still مقترح (pending) but
    had examiners/evaluations/a final score already attached to it, which is now structurally
    impossible (examiners/evaluations can only ever attach to an instantiated `projects` row, not
    a proposal-only id) — id=1 is cleaned (graded data stripped) before being copied forward as a
    proposal-only row. Final counts: 25 proposals, 20 projects (the 20 originally-مؤرشف rows each
    get exactly one instantiated project); legacy `project_students`/`projects_legacy` tables
    dropped after verification.
  - `visit_count`/`instantiated_by`(+`instantiated_at`) both live on `projects`, not `proposals`:
    `visit_count` is inherently a view-count on the instantiated/archived work (public browse/show
    only ever surface instantiated projects), and `instantiated_by`/`_at` record who/when performed
    the instantiate action that produced that specific project row — nullable, since the 20 rows
    backfilled by the data migration command have no real "who clicked" actor.
  - New route names: `proposals.index/create/store/show/edit/update/destroy` (full resource) +
    `POST proposals/{id}/instantiate` named `proposals.instantiate`; `projects.index`/
    `projects.show` only (GET, thin — no create/store/edit/update/destroy on `Project` at all;
    project creation/replace/delete all happen on the owning `Proposal` instead).
  - The "no UI exists to move a project from قيد التنفيذ to مؤرشف" gap flagged here was
    intentionally out of scope for this change. It was closed by the
    `2026-08-25-project-finalize-archive` change — see the "Current Status" entry above and
    `.superpowers/sdd/2026-08-25-project-finalize-archive/` for the full per-task history.
  - This entry supersedes/completes Tasks 1-7 of the `2026-08-24-proposal-project-split` plan
    (schema, models, data migration, controllers/routes, frontend, and full test-suite realignment
    — see `.superpowers/sdd/2026-08-24-proposal-project-split/` for the full per-task history).
- 🐛 **Bugfix — Ghost "في انتظار الموافقة" status badge** — the prior Paper-Approval Proposal
  Lifecycle change (below) updated the DB, backend authorization, and both Vue pages to the
  2-status model, but missed `resources/js/composables/useProjectStatus.ts`, which still mapped
  the "مقترح" status to the display label "في انتظار الموافقة" (yellow) instead of "مقترح" (blue).
  This produced a third, non-existent status badge in production even though only 2 statuses have
  ever existed in the DB (confirmed via direct query: `project_status` has exactly 2 rows, all 25
  seeded projects reference a valid `current_status_id`). Fixed the label/color map and renamed
  `isPendingApproval()` → `isProposalStatus()` (no digital "approval" concept exists — see the
  paper-approval bullet above). Also removed the same "awaiting approval" phrase from the
  super_admin dashboard's stat card title (`resources/js/pages/Dashboard.vue`), which counted
  "مقترح" projects but labeled them as "awaiting approval". **"في انتظار الموافقة" is not a valid
  system state — it must never appear anywhere in the UI.**
- ✅ Paper-Approval Proposal Lifecycle — 238/238 total suite (0 failures)
  - Migration `2026_08_23_120000_drop_approval_gate_columns_from_projects_table.php` — drops
    supervisor_approved_by/_at, department_approved_by/_at (never referenced outside the model
    and a since-deleted test — approval is paper-based, not tracked digitally)
  - Migration `2026_08_23_120100_add_created_by_to_projects_table.php` — adds nullable
    created_by FK to users; set from auth()->id() in ProjectController::store()
  - Project model — STATUS_ARCHIVED/STATUS_PENDING public constants (single source of truth,
    replacing the private copies in ProjectController); canBeModifiedBy()/canBeArchivedBy()
    encode the full permission matrix once, used by both FormRequests and the edit() gate
  - UpdateProjectRequest/new DeleteProjectRequest/new ArchiveProjectRequest — replace/delete
    locked to current_status_id=2 (مقترح) + creator-or-dept_manager-of-that-department;
    archive locked to dept_manager-of-that-department + مقترح status (idempotency guard against
    re-archiving); super_admin bypasses every check
  - ProjectController::approve() renamed to archive() (route projects.approve → projects.archive)
    — "approve" no longer describes any digital action now that approval is paper-based
  - Projects/Show.vue + Index.vue — canReplace/canDeleteProject/canArchive are now ownership-
    and status-aware (created_by + department_id + current_status_id), replacing the old blanket
    role-only canEdit/canDelete/canApprove; archive button relabelled أرشفة (was اعتماد)
  - Fixed two latent bugs found in the same pass: dept_manager could previously archive another
    department's pending project (no dept scoping on the old approve route), and dept_manager
    could edit/delete an already-archived project in their own department (no status lock at all)
  - tests/Feature/Models/ProjectModelTest.php — 9 new tests for canBeModifiedBy()/canBeArchivedBy()
  - tests/Feature/Project/ProjectTest.php — 11 new tests covering the full create/replace/
    delete/archive permission matrix
  - tests/Feature/Roles/RoleVerificationTest.php — updated 2 fixtures whose old expectations
    (dept_manager deleting an archived project; dept_staff editing a project with no created_by
    set) were superseded by the new rules; renamed projects.approve references to projects.archive
- ✅ Project Lifecycle Scope-Down — 220/220 total suite (0 failures; the 10 previously-noted ext-zip failures no longer reproduce in this environment)
  - Migration `2026_08_17_220723_add_approval_gates_to_projects_table.php` — adds nullable `supervisor_approved_by`/`supervisor_approved_at`/`department_approved_by`/`department_approved_at` to `projects` (FKs nullOnDelete to users); original `projects`/`project_status` migrations untouched
  - ProjectStatusSeeder — narrowed from 10 rows to exactly 2: id=1 "مؤرشف" (archived, sort_order=2), id=2 "مقترح" (proposal, sort_order=1). IDs kept as originally seeded — id=1 already meant "archived" everywhere (ProjectController, PublicController, SearchService, ReportService, ProjectsImport) — only the Arabic text/sort_order changed, avoiding a much larger, riskier flip of existing business logic
  - DummyDataSeeder — 3 demo projects that referenced removed statuses 5/6 (in_progress, ready_for_defense) reassigned to status_id 2 (مقترح); dev DB rebuilt via `migrate:fresh --seed`
  - Project model (app/Models/Project.php) — added supervisor_approved_by/_at, department_approved_by/_at to fillable/casts; supervisorApprovedBy()/departmentApprovedBy() BelongsTo relationships; isSupervisorApproved()/isDepartmentApproved() helper methods
  - Projects/Index.vue + Projects/Show.vue — STATUS_COLORS/STATUS_LABELS maps and the `canApprove` gate were keyed on the old English status_name values (archived/proposal_submitted) plus 8 dead Phase-2 keys; updated to match the new Arabic status_name values so the Approve button and status badges keep working
  - tests/Feature/Seeders/ProjectStatusSeederTest.php — rewritten (was asserting the old 10-status/English-name spec, which this change intentionally supersedes)
  - tests/Feature/Project/ProjectApprovalTest.php — new, 2 tests: default status + null approvals on creation; both approval fields settable and status manually movable to archived
  - STATUS_HISTORY and DEFENSE audited codebase-wide: never implemented (planning docs only, no migration/model/controller) — nothing removed because nothing existed. PROJECT_DOCUMENT (`project_documents` table) exists and remains fully in scope, unrelated to this change.
- ✅ Public Browse + Auth Flow Fix — 208/218 total suite (10 pre-existing Import ext-zip failures)
  - PublicController (app/Http/Controllers/PublicController.php) — index() passes real stats to Welcome; browse() filters archived projects (status_id=1, not deleted) by search/dept/spec/year, paginates 12; show() aborts 404 if not archived/deleted, increments visit_count, returns related projects
  - routes/web.php — GET / → PublicController::index (home), GET /browse → public.browse, GET /browse/{id} → public.show; all outside auth middleware
  - routes/auth.php — /register GET+POST → redirect to /login with error flash (registration disabled for public)
  - Pages/Public/Browse.vue — public layout (no sidebar), header with logo+login button, search+dept+spec+year filters, 3-col project grid, pagination, empty state
  - Pages/Public/Show.vue — public layout, back link, project header (title+status badge+visit count), info grid, students table, description, PDF download, related projects
  - pages/Welcome.vue — stats prop passed from controller (real counts); statsRows computed(); CTA buttons updated: "تصفح المشاريع" → public.browse, "تسجيل الدخول" → login
  - components/AppSidebar.vue — BookOpen import added; "تصفح المشاريع" → /browse added to all role nav menus
  - tests/Feature/Public/PublicBrowseTest.php — 14/14 passing: landing, browse, search, dept/year filters, show archived, 404 non-archived/deleted, visit_count increment, dashboard/projects/register blocked for guests
  - tests/Feature/Auth/RegistrationTest.php — updated to assert 302 redirect to login (registration disabled)
- ✅ Department Permission Bug Fix — dept_manager now correctly READ-ONLY on other depts
  - routes/web.php — store/destroy/create moved to super_admin-only middleware group; index/edit/update remain under super_admin,dept_manager
  - DepartmentController::edit() + update() — abort(403) if dept_manager tries to access dept other than their own
  - Departments/Index.vue — role-based conditional buttons: "إضافة قسم" hidden for dept_manager; Edit/Delete/+Spec per-row conditionally shown based on role + ownership (department_id match)
  - types/index.ts — User interface extended with department_id?: number | null
  - DepartmentTest::dept_manager can create department → updated to assert 403 (was asserting success, contradicted business rules)
- ✅ Role Verification Tests — 58/58 passing (194/204 total suite; 10 pre-existing Import failures due to ext-zip disabled in XAMPP)
  - tests/Feature/Roles/RoleVerificationTest.php — 58 tests, 80 assertions across all 5 roles
  - super_admin: 15 tests — full access verified; can delete empty dept, blocked on dept-with-projects
  - dept_manager: 15 tests — cannot create/delete depts (403); can update own dept; cannot update other dept; dept access + project lifecycle + examiners + score; cannot import
  - supervisor: 11 tests — read-only projects/dashboard; all write operations blocked
  - dept_staff: 13 tests — create/edit pending projects in own dept; cross-dept and post-approval blocked
  - viewer: 6 tests — dashboard only; all write/manage/report routes blocked
  - Bug fixed: ProjectController::authorizeEdit() now enforces dept_id check for dept_staff (was only checking pending status, allowing cross-department edits)
- ✅ UI/UX Phase 1d — Auth Pages Arabic Translation + College Logo
  - AuthSimpleLayout.vue — replaced AppLogoIcon SVG with `<img src="/images/logo.png">` (h-20, college logo)
  - pages/auth/Login.vue — fully translated to Arabic; RTL-correct label/link order (label RIGHT, forgot-password LEFT); `text-right` inputs; `تذكرني` checkbox with `justify-end`
  - pages/auth/Register.vue — fully translated to Arabic; all labels/placeholders in Arabic; `text-right` inputs
  - Font check: Tajawal/IBM Plex Sans Arabic applied globally via app.blade.php `dir=rtl` — auth pages inherit correctly
- ✅ UI/UX Phase 1c — RTL Sidebar Gap Fix
  - SidebarProvider.vue — removed `flex-row-reverse`; in RTL (`dir=rtl`) plain `flex` already flows right→left, placing sidebar spacer on the RIGHT naturally. `flex-row-reverse` in RTL caused double-reversal (spacer ended up on LEFT creating 256px gap)
  - SidebarInset.vue — changed `ml-0`/`ml-2` to `me-0`/`me-2` (logical `margin-inline-end`) so the zero-margin side correctly tracks the non-sidebar edge in both LTR and RTL
- ✅ UI/UX Phase 1b — RTL Layout Fix + Landing Page
  - Sidebar.vue (AppSidebar) — `side="right"` so fixed panel anchors to right edge
  - AppSidebar.vue — uses `Logo.vue` (brand), removed AppLogo/Documentation link, clean footer
  - AppSidebarHeader.vue — trigger moved to right end (justify-between), breadcrumbs left
  - NavUser.vue — `ml-auto`→`me-auto` for RTL-correct chevron
  - resources/css/app.css — sidebar CSS vars updated to brand palette: white bg, primary blue active, light blue hover
  - pages/Welcome.vue — full Arabic landing page: sticky header, hero (dark blue gradient + dot grid), stats row, 4-feature grid, CTA band, footer
  - Note: `@/Components/Brand/Logo.vue` casing error is stale TS cache — all imports now lowercase `@/components/Brand/Logo.vue`
- ✅ UI/UX Phase 1 — Design System Setup
  - tailwind.config.js — brand color palette (primary/dark/light #103A52/#1B6B93/#4FA8C9, surface, background, border, text-dark, text-muted) + display/body fontFamily (Tajawal, IBM Plex Sans Arabic)
  - resources/css/app.css — Google Fonts @import for Tajawal (400/500/700/800) + IBM Plex Sans Arabic (400/500/600)
  - resources/views/app.blade.php — html lang="ar" dir="rtl"; body uses font-body + bg-background + text-text-dark; Google Fonts preconnect headers
  - resources/js/Components/Brand/Logo.vue — size prop (sm/md/lg); placeholder initials block (ك.ت) until real image added
  - resources/js/pages/DesignSystem.vue — dev-only page at /design-system (no auth): color swatches, typography scale, button variants, card patterns
  - routes/web.php — GET /design-system (no auth, remove before production)
  - Note: existing shadcn/ui CSS-variable tokens (foreground, card, popover, secondary, muted, accent, destructive, sidebar) are preserved for component compatibility; brand hex tokens override primary/background/border defaults
- ✅ Phase I Part 3 — Pest Tests for User Management — 9/9 passing (146/146 total suite)
  - tests/Feature/Admin/UserManagementTest.php — 9 tests, 47 assertions:
    - super_admin can view users list (assertInertia component check)
    - super_admin can filter users by role (where users.meta.total = 1)
    - super_admin can create user with role (DB + hasRole assertion)
    - super_admin can update user info (name + syncRoles)
    - super_admin can toggle user active status (true → false)
    - super_admin cannot delete the only super_admin (error flash, user preserved)
    - super_admin can delete a non-admin user (assertDatabaseMissing)
    - dept_manager cannot access admin users (assertForbidden)
    - unauthenticated user redirected from admin users (→ /login)
  - Fixed: UserFactory missing is_active field (was returning null instead of DB default true)
- ✅ Phase I Part 2 — User Management Frontend (Admin)
  - UserController::index() updated: returns { data, links, meta: { current_page, last_page, total, per_page } } (transformed paginator)
  - Pages/Admin/Users/Index.vue — full rewrite per Part 2 spec:
    - Filter bar: search (400ms debounce) + role/dept/is_active selects → preserveScroll:true router.get
    - Table: 8 columns (#, الاسم, البريد, رقم القيد, القسم, الدور, الحالة, الإجراءات)
    - Role badge colours: super_admin=red, dept_manager=blue, supervisor=green, dept_staff=yellow, viewer=gray
    - Role labels: مدير النظام / مدير القسم / مشرف / موظف القسم / مشاهد
    - Active badge: green "نشط" / red "غير نشط"
    - Actions: تعديل (opens EditUserModal) | إيقاف/تفعيل (router.patch toggle-active) | حذف (ConfirmDelete)
    - CreateUserModal: separate useForm (createForm) with is_active checkbox
    - EditUserModal: separate useForm (editForm), password optional, no is_active
    - Pagination via users.links + users.meta.*
- ✅ Phase I Part 1 — User Management Backend (Admin)
  - app/Http/Controllers/Admin/UserController.php — index/store/update/toggleActive/destroy
  - app/Http/Requests/StoreUserRequest.php + UpdateUserRequest.php
  - routes/web.php — Route::resource('users') + toggle-active PATCH under admin.* group
- ✅ Phase H Part 3 — Pest Tests for Reports
  - tests/Feature/Report/ReportTest.php — 13 tests, 138 assertions, all passing
  - Tests cover: dashboard role-based stats (super_admin/dept_manager/dept_staff), department report access control (dept_manager forced to own dept, super_admin can filter), specialization ordering, supervisor avg_score calculation, yearly growth%, PDF/Excel exports, unauthenticated redirect
  - Fixed ReportController: exportPdf() return type changed from StreamedResponse → \Illuminate\Http\Response (dompdf returns standard Response); $request->get() → $request->input() (deprecated); removed unused StreamedResponse import
- ✅ Phase H Part 2 — Dashboard + Reports Vue Pages
  - ReportService.getDashboardStats() updated: added pending_approvals (status_id=2) field
  - components/StatsCard.vue — reusable card: title, value, icon (Component), sub, color (blue/green/purple/orange)
  - components/ExportButtons.vue — two download links: PDF (red) + Excel (green); props: pdfUrl, excelUrl
  - Pages/Dashboard.vue — full role-based rebuild:
    - super_admin: 4 stats cards + recent-projects table (5 rows) + CSS status bar chart + report quick-links grid
    - dept_manager: 3 stats cards + specializations table + supervisors list + report quick-links
    - default: single stat card + link to /projects
    - Fixed auth access: page.props.auth?.user?.role (optional chaining for SharedData index signature)
  - Pages/Reports/Department.vue — dept filter (super_admin only, router.get), summary cards, departments table, per-dept specialization breakdown with % bar, supervisors table, ExportButtons
  - Pages/Reports/Specializations.vue — top-10 CSS bar chart, year-trend table with mini bars, rarely-used specs table, ExportButtons
  - Pages/Reports/Supervisors.vue — client-side dept filter + client-side 3-column sort (name/project_count/avg_score ↑↓), by_year chips per supervisor, ExportButtons
  - Pages/Reports/Yearly.vue — year comparison table with TrendingUp/TrendingDown icons + growth%, dept×year matrix table, summary cards, ExportButtons
  - components/AppSidebar.vue — BarChart2 icon added; التقارير nav link added to super_admin and dept_manager menus (→ /reports/department)
  - Note: BarChart2 import shows Volar incremental hint (false positive); noUnusedLocals is OFF in tsconfig.json
- ✅ Phase H Part 1 — Reports Backend
  - composer require barryvdh/laravel-dompdf (installed, ^3.1)
  - ReportService (app/Services/ReportService.php) — 5 methods:
    - getDashboardStats(): total_projects, total_departments, projects_this_year, recent 5, by_status breakdown
    - getDepartmentReport(?int $departmentId): departments with project_count+avg_score+specializations, supervisors list
    - getSpecializationTrends(): top 10, rare bottom 5, by_year grouped data
    - getSupervisorReport(): all supervisors with project_count, avg_score, by_year breakdown
    - getYearlyComparisonReport(): yearly counts + growth %, department_by_year distribution
  - ReportExport (app/Exports/ReportExport.php) — FromArray+WithHeadings+WithTitle+WithStyles
  - PDF Blade view (resources/views/exports/report.blade.php) — RTL Arabic, table, dompdf compatible
  - ReportController (app/Http/Controllers/ReportController.php) — 7 methods:
    - dashboard(): role-based (super_admin=full stats, dept_manager=dept stats, default=basic)
    - departmentReport(): dept_manager sees own dept only; super_admin can filter → Reports/Department.vue
    - specializationReport() → Reports/Specializations.vue
    - supervisorReport() → Reports/Supervisors.vue
    - yearlyReport() → Reports/Yearly.vue
    - exportPdf(type): dompdf download via exports.report Blade view
    - exportExcel(type): maatwebsite/excel xlsx download via ReportExport
  - routes/web.php: dashboard route → ReportController; /reports/* group under role:dept_manager,super_admin
  - Note: project_status table name is 'project_status' (not plural), status column is 'status_name'
- ✅ Laravel project created
- ✅ Vue + Inertia installed
- ✅ Spatie Permissions installed
- ✅ Authentication working
- ✅ All migrations created and finalized (14 migrations, column names aligned to spec)
- ✅ Database connected to MariaDB (graduation_archive) — config cache issue resolved
- ✅ All 9 Eloquent models created with fillable, casts, and relationships
- ✅ User model updated — HasRoles trait, registration_number, department_id, is_active
- ✅ Spatie RBAC seeded — 5 roles: super_admin, dept_manager, supervisor, dept_staff, viewer
- ✅ ProjectStatus seeded — 10 statuses (archived → cancelled)
- ✅ Admin user seeded — admin@admin.com / password / role: super_admin
- ✅ Phase A Pest tests — 45/45 passing (Auth, Models, Seeders)
- ✅ Phase B RBAC Middleware + Role-based Redirects — 54/54 passing
  - RoleMiddleware (app/Http/Middleware/RoleMiddleware.php) — checks role, 401→login, wrong role→403
  - Middleware aliases registered in bootstrap/app.php: 'role', 'permission'
  - Route groups: public, auth+verified (dashboard), super_admin (/admin), dept_manager (/departments), dept_staff (/projects/create)
  - DashboardController — role-based stats (super_admin: system totals, dept_manager: dept stats, supervisor: their projects, default: basic)
  - Dashboard.vue updated — shows user name, role, stats cards in Arabic RTL
  - HandleInertiaRequests shares user role via getRoleNames()->first()
  - RBACTest — 9 tests covering access grants and 403 blocks
- ✅ Phase C Department & Specialization Management — 66/66 passing
  - Migration: description column added to departments table
  - DepartmentController (resource) — index/create/store/edit/update/destroy with project-link guard
  - SpecializationController — store/update/destroy with project-link guard
  - Form Requests: StoreDepartmentRequest, UpdateDepartmentRequest, StoreSpecializationRequest, UpdateSpecializationRequest
  - Routes: Route::resource('departments') + Route::resource('specializations')->only([store,update,destroy]) under role:super_admin,dept_manager
  - Vue pages: Departments/Index.vue (table + inline spec management + modals), Create.vue, Edit.vue
  - Shared components: Modal.vue, DataTable.vue, ConfirmDelete.vue
  - AppSidebar.vue — role-based nav (super_admin/dept_manager/dept_staff/supervisor menus)
  - NavMain.vue — fixed url→href mismatch; label changed to Arabic
  - SharedData type — added index signature + flash prop
  - Factories: DepartmentFactory, SpecializationFactory, ProjectFactory
  - HasFactory added to Department, Specialization, Project models
  - DepartmentTest — 14 tests covering CRUD, access control, validation, specializations
- ✅ Phase D Part 1 — ProjectController + Routes
  - StoreProjectRequest, UpdateProjectRequest (app/Http/Requests/)
  - ProjectController — index/create/store/show/edit/update/destroy/approve (8 methods)
  - store: dept_manager/super_admin → status archived(1); dept_staff → proposal_submitted(2)
  - approve(): changes status to archived(1); middleware role:dept_manager,super_admin
  - destroy(): soft delete via is_deleted=true; dept_manager/super_admin only
  - authorizeEdit(): super_admin=any, dept_manager=own dept, dept_staff=pending projects only
  - PDF upload to storage/public/projects, max 15MB; also creates ProjectDocument record
  - Route::resource('projects') + approve route under auth middleware
- ✅ Phase D Part 2 — Project Vue Pages
  - ProjectController::index() updated with withCount('students') for table column
  - @var \App\Models\User casts added to silence static analysis on hasRole/hasAnyRole
  - Pages/Projects/Index.vue — search (debounced 400ms), dept/year filters, paginated table,
    status badge, similarity warning (duplicate title detection), role-based action buttons
  - Pages/Projects/Create.vue — full form, dynamic students (add/remove rows), cascaded
    dept→spec select, PDF upload with progress bar, forceFormData for nested array+file
  - Pages/Projects/Edit.vue — pre-filled form, current PDF display with download link,
    replace-PDF upload, form.put() with forceFormData
  - Pages/Projects/Show.vue — 2-column layout, description, students table, examiners,
    evaluations, PDF download, final score, visit counter, role-based action buttons
- ✅ Phase E Part 1 — Search + Filter Backend
  - SearchService (app/Services/SearchService.php) — 3 methods:
    - searchProjects(array $filters): paginated results with dept/spec/year/supervisor/status/sort filters + full-text LIKE on title+description
    - detectSimilarity(string $title, ?int $excludeId): returns up to 5 projects with similar title using LIKE
    - getFilterOptions(): returns departments (with specializations), academic_years (distinct), supervisors
  - ProjectController refactored — constructor-injected SearchService; index() uses searchProjects + getFilterOptions; store()/update() call detectSimilarity and flash similarity_warning if matches found (saving still proceeds)
  - SearchController (app/Http/Controllers/SearchController.php) — index() → Pages/Search/Index.vue; suggestions() → JSON autocomplete (top 5 by visit_count, min 2 chars)
  - Routes added under auth middleware: GET /search (search.index), GET /search/suggestions (search.suggestions)
  - HandleInertiaRequests — flash keys (success, error, similarity_warning) now globally shared
- ✅ Phase E Part 2 — Search Vue Pages + Components
  - similarity_warning flash updated to store full objects {id, project_title, academic_year, department} in ProjectController
  - SearchController::index() eager-loads students (id, full_name) via loadMissing for Search page
  - types/index.ts — SimilarProject interface exported; SharedData.flash extended with similarity_warning: SimilarProject[]
  - components/SearchBar.vue — reusable: v-model, suggestions dropdown, debounce 300ms, clear button, @search/@select emits
  - components/FilterPanel.vue — collapsible panel: dept+spec cascade (spec disabled until dept chosen), year, supervisor, status, sort; active-filter count badge; reset button; exports FilterValues type
  - components/SimilarityWarning.vue — banner with links to each similar project; @continue/@change-title emits
  - pages/Projects/Index.vue — fully rebuilt: uses SearchBar + FilterPanel + SimilarityWarning; filterOptions prop replaces departments; active filter chips (clickable ×); results count; debounced live search calls suggestions endpoint
  - pages/Search/Index.vue — global search page: SearchBar with live suggestions, results grouped by department, project cards with highlighted match text (v-html + HTML-escaped), student name tags, pagination
  - components/AppSidebar.vue — "البحث" nav link added to all role menus using Lucide Search icon
- ✅ Phase E Part 3 — Pest Tests for Search + Filter — 15/15 passing (96/96 total suite)
  - tests/Feature/Search/SearchTest.php — 15 tests, 150 assertions:
    - search by title / by description / case insensitive
    - filter by department / specialization / academic year / supervisor
    - combine multiple filters / empty search returns all / paginated results
    - similarity detection finds matching titles / ignores current project on edit
    - suggestions returns max 5 / matching titles only / unauthenticated blocked
  - Helper: makeSearchProject(?dept, ?spec, ?supervisor, overrides[]) — creates project with all FK deps
  - SearchService tested directly (detectSimilarity); HTTP routes tested via projects.index, search.index, search.suggestions
  - SQLite in-memory: LIKE is case-insensitive for ASCII — case sensitivity test passes
- ✅ Phase G Part 3 — Pest Tests for Bulk Import — 15/15 passing (124/124 total suite)
  - tests/Feature/Import/ImportTest.php — 15 tests, 52 assertions:
    - Access: super_admin allowed, dept_manager 403
    - Template: assertDownload on xlsx response
    - Validation: non-excel file rejected, file >5MB rejected (both via HTTP layer)
    - Valid import: project + correct status (archived) created
    - Per-row isolation: bad dept_code / bad specialization / bad supervisor email / missing title each fail only that row
    - Students: 3 students created correctly; empty slots skipped without error
    - Summary: correct total/success/fail counts with mixed rows
    - Preview vs import: dryRun:true touches 0 DB rows; dryRun:false writes 1 project
  - Helper functions: makeImportFile() (real xlsx via PhpSpreadsheet), makeImportDeps(), validImportRow()
  - Business-logic tests use Excel::import() directly (avoids HTTP mimes/extension detection variance)
  - HTTP tests cover access control + Laravel file validation rules
- ✅ Phase G Part 2 — Import Vue Wizard
  - Pages/Import/Index.vue — 4-step wizard (step indicator header with green/blue progress)
  - Step 1: Download template + 13-column reference table (required badge, Arabic labels)
  - Step 2: File upload → preview POST (preserveState:true) → stats cards + first-10-rows table with ✓/✗ per row + full error list + "Continue" button
  - Step 3: Pre-import summary → import POST (preserveState:true, spinner) → results (success/fail cards) + failed rows table + CSV error download (client-side Blob) + "Upload PDFs" / "Import More" buttons
  - Step 4: ZIP upload → PDF match results (matched count + unmatched list)
  - State persists across Inertia visits via preserveState:true; flash.preview/import_summary/pdf_summary watched and saved to local refs
  - ProjectsImport refactored: validate() method extracted, previewRows collected (max 10) during dryRun, getSummary() includes preview_rows
  - HandleInertiaRequests: preview, import_summary, pdf_summary flash keys now shared
- ✅ Phase G Part 1 — Bulk Import Backend
  - composer require maatwebsite/excel 3.1.69 (enabled ext-gd in php.ini)
  - app/Exports/ProjectImportTemplate.php — FromArray+WithHeadings+WithStyles; 13 columns, example row in yellow, header in blue
  - app/Imports/ProjectsImport.php — ToCollection+WithHeadingRow; validates required fields, finds dept/spec/supervisor, creates Project+students; dryRun flag for preview; getSummary() returns {total_rows,success_count,failed_count,failed_rows}
  - app/Http/Controllers/ImportController.php — index/downloadTemplate/preview/import/uploadPdfs (ZIP→PDF matching by title)
  - routes/web.php — 5 routes under middleware auth+role:super_admin prefix /import
  - Pages/Import/Index.vue — 4-step UI: download template, preview, import, PDF ZIP upload
  - AppSidebar.vue — "استيراد" link (Upload icon) added to super_admin nav
- ✅ Phase F — Examiners + Scores — 109/109 total suite passing
  - Phase F Part 1 — Backend Controllers + Routes:
    - ExaminerController (index/store/update/destroy) with dept filter + projects-link guard
    - ProjectExaminerController (assign/remove) — max-2 cap, duplicate guard, assigned_by pivot
    - EvaluationController (store/updateScore) — notes per examiner, final_score on project
    - 5 Form Requests: StoreExaminerRequest, UpdateExaminerRequest, AssignExaminerRequest, StoreEvaluationRequest, UpdateScoreRequest
    - Routes under role:super_admin,dept_manager middleware group
  - Phase F Part 2 — Vue Pages:
    - pages/Examiners/Index.vue — table (name/title/dept/projects count), dept filter, add+edit modal, delete confirm
    - components/AssignExaminerModal.vue — dropdown of unassigned examiners, POST to assign-examiner
    - components/ScoreInput.vue — score display + pass/fail badge (threshold 50) + PATCH form
    - pages/Projects/Show.vue — fixed Examiner interface (full_name), assign/remove examiner UI,
      per-examiner evaluation notes, ScoreInput for managers / read-only for others
    - AppSidebar.vue — الممتحنون link (UserCheck icon) for super_admin + dept_manager
  - Phase F Part 3 — Pest Tests — 13/13 passing (109/109 total):
    - tests/Feature/Examiner/ExaminerTest.php — 13 tests, 42 assertions:
      dept_manager create/filter; dept_staff blocked; validation; assign/max-2/duplicate/remove;
      evaluation notes; final score set/blocked/range; cannot delete linked examiner
    - Bug fixed: evaluations migration had wrong columns (score+evaluated_by) → replaced with examiner_id+notes
    - ExaminerFactory created; HasFactory added to Examiner model
    - Helper: makeExaminerProject() — creates dept+spec+supervisor+project+examiner

## Do NOT
- Do not use deleted_at for soft delete (use is_deleted boolean)
- Do not create REST API endpoints (use Inertia responses)
- Do not store every draft (only milestone documents)
- Do not allow mobile layout (desktop only)
- Do not mix Arabic in code (English only in code, Arabic in UI text)
