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
5. projects — central evolving entity
6. project_students (weak entity, with status/withdrawal)
7. project_documents — milestone docs only (no drafts)
8. examiners
9. project_examiners (M:N junction)
10. project_status — reference table
11. evaluations

Phase 2 (Future):
12. student_eligibility
13. defense
14. status_history
15. supervisor_history

## Roles (RBAC via Spatie)
1. super_admin — full access
2. dept_manager — manage own department
3. supervisor — approval gates only (no data entry)
4. dept_staff — add projects (pending approval)
5. viewer — browse only (Phase 2)

## Key Business Rules
- One project = one specialization, one supervisor
- Fixed 2 examiners per project
- Final score entered by dept_manager only
- PDF files only, max 15MB
- Soft delete on projects (is_deleted field)
- Project is single evolving entity (proposal → archived)
- Only milestone documents saved (not every draft)
- dept_staff projects need dept_manager approval before publishing
- Supervisor role = approval gates in lifecycle (no CRUD)
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
- Full project lifecycle (11 stages)
- Supervisor approval gates
- Defense scheduling
- Document milestone tracking
- Status history logging

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
