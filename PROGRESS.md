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
| password | varchar(255) | No | — | Hashed password |
| remember_token | varchar(100) | Yes | null | Remember-me token |
| registration_number | varchar(255), unique | Yes | null | College registration number |
| department_id | bigint unsigned, FK | Yes | null | FK to departments.id (nullOnDelete) |
| is_active | boolean | No | true | Account active flag |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** `department_id` references departments(id), nullOnDelete.
**Spatie tables:** `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `roles`, `permissions` are created by the Spatie migration.

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

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | tinyint unsigned, PK | No | auto | Primary key (tiny int) |
| status_name | varchar(255), unique | No | — | Status identifier string |
| sort_order | tinyint unsigned | No | 0 | Display order |
| is_active | boolean | No | true | Active flag |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Seeded statuses (from ProjectStatusSeeder):**

| id | status_name | sort_order | is_active |
|---|---|---|---|
| 1 | archived | 8 | true |
| 2 | proposal_submitted | 1 | false |
| 3 | supervisor_approved | 2 | false |
| 4 | hod_approved | 3 | false |
| 5 | in_progress | 4 | false |
| 6 | ready_for_defense | 5 | false |
| 7 | under_defense | 6 | false |
| 8 | revisions_required | 7 | false |
| 9 | rejected | 9 | false |
| 10 | cancelled | 10 | false |

Note: Only `archived` (id=1) has is_active=true. The rest are Phase 2 statuses.

### Table: projects

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| project_title | varchar(255) | No | — | Project title (Arabic) |
| description | text | Yes | null | Long description |
| academic_year | varchar(9) | No | — | e.g., "2024/2025" |
| department_id | bigint unsigned, FK | No | — | FK to departments.id |
| specialization_id | bigint unsigned, FK | No | — | FK to specializations.id |
| supervisor_id | bigint unsigned, FK | No | — | FK to users.id |
| current_status_id | tinyint unsigned, FK | No | — | FK to project_status.id |
| based_on_project_id | bigint unsigned, FK | Yes | null | FK to projects.id (nullOnDelete) — evolution link |
| draft_file_path | varchar(255) | Yes | null | Path to uploaded PDF in public storage |
| final_score | decimal(5,2) | Yes | null | Final score out of 100 |
| visit_count | int unsigned | No | 0 | Page view counter |
| is_deleted | boolean | No | false | Soft delete flag |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** `department_id` to departments, `specialization_id` to specializations, `supervisor_id` to users, `current_status_id` to project_status.id, `based_on_project_id` to projects.id (self-referential, nullOnDelete).

### Table: project_students

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| project_id | bigint unsigned, FK | No | — | FK to projects.id (cascadeOnDelete) |
| full_name | varchar(255) | No | — | Student full name |
| registration_number | varchar(255) | No | — | Student registration number |
| status | varchar(255) | No | active | Student status: active, withdrawn, completed |
| withdrawal_date | date | Yes | null | Date of withdrawal if withdrawn |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** `project_id` references projects.id, cascadeOnDelete.

### Table: project_documents

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| id | bigint unsigned, PK | No | auto | Primary key |
| project_id | bigint unsigned, FK | No | — | FK to projects.id (cascadeOnDelete) |
| document_type | varchar(255) | No | — | e.g., "final_report" |
| file_path | varchar(255) | No | — | Storage path |
| approved_by | bigint unsigned, FK | Yes | null | FK to users.id (nullOnDelete) |
| is_final | boolean | No | false | Whether this is the final version |
| approved_at | timestamp | Yes | null | When it was approved |
| created_at | timestamp | Yes | null | — |
| updated_at | timestamp | Yes | null | — |

**Foreign keys:** `project_id` references projects.id (cascadeOnDelete), `approved_by` references users.id (nullOnDelete).

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
- **Fillable:** name, email, password, registration_number, department_id, is_active
- **Hidden:** password, remember_token
- **Casts:** email_verified_at (datetime), password (hashed), is_active (boolean)
- **Relationships:**
  - `department()` — BelongsTo(Department), FK: department_id
  - `supervisedProjects()` — HasMany(Project), FK: supervisor_id

### app/Models/Department.php

- **Table:** departments
- **Traits:** HasFactory
- **Fillable:** name, code, description
- **Relationships:**
  - `specializations()` — HasMany(Specialization)
  - `users()` — HasMany(User)
  - `projects()` — HasMany(Project)
  - `examiners()` — HasMany(Examiner)

### app/Models/Specialization.php

- **Table:** specializations
- **Traits:** HasFactory
- **Fillable:** name, department_id
- **Relationships:**
  - `department()` — BelongsTo(Department)
  - `projects()` — HasMany(Project)

### app/Models/Project.php

- **Table:** projects
- **Traits:** HasFactory
- **Fillable:** project_title, description, academic_year, department_id, specialization_id, supervisor_id, current_status_id, based_on_project_id, draft_file_path, final_score, visit_count, is_deleted
- **Casts:** is_deleted (boolean), final_score (decimal:2)
- **Relationships:**
  - `department()` — BelongsTo(Department)
  - `specialization()` — BelongsTo(Specialization)
  - `supervisor()` — BelongsTo(User), FK: supervisor_id
  - `currentStatus()` — BelongsTo(ProjectStatus), FK: current_status_id
  - `basedOn()` — BelongsTo(Project), FK: based_on_project_id (self-referential)
  - `students()` — HasMany(ProjectStudent)
  - `documents()` — HasMany(ProjectDocument)
  - `evaluations()` — HasMany(Evaluation)
  - `examiners()` — BelongsToMany(Examiner, pivot: project_examiners), using ProjectExaminer pivot model, withPivot('assigned_by'), withTimestamps()

### app/Models/ProjectStatus.php

- **Table:** project_status (non-standard table name, set explicitly with `$table = 'project_status'`)
- **Fillable:** status_name, sort_order, is_active
- **Casts:** is_active (boolean)
- **Relationships:**
  - `projects()` — HasMany(Project), FK: current_status_id

### app/Models/ProjectStudent.php

- **Table:** project_students
- **Fillable:** project_id, full_name, registration_number, status, withdrawal_date
- **Casts:** withdrawal_date (date)
- **Relationships:**
  - `project()` — BelongsTo(Project)

### app/Models/ProjectDocument.php

- **Table:** project_documents
- **Fillable:** project_id, document_type, file_path, approved_by, is_final, approved_at
- **Casts:** is_final (boolean), approved_at (datetime)
- **Relationships:**
  - `project()` — BelongsTo(Project)
  - `approvedBy()` — BelongsTo(User), FK: approved_by

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

### app/Http/Controllers/DashboardController.php

**Purpose:** Renders the dashboard with role-based statistics (this controller is present but the /dashboard route now maps to ReportController::dashboard()).

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /dashboard | Request | Builds stats array based on role | Inertia: Dashboard | All authenticated |

Role branching: super_admin gets total_users, total_projects, total_departments; dept_manager gets dept_projects count; supervisor gets supervised_projects count; default gets total_projects.

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
| destroy() | DELETE /departments/{dept} | Department | Blocks if dept has projects; calls dept->delete() | Redirect to index | super_admin |

### app/Http/Controllers/SpecializationController.php

**Purpose:** Create, update, delete specializations (inline in department management).

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| store() | POST /specializations | StoreSpecializationRequest | Specialization::create() | Redirect back | super_admin, dept_manager |
| update() | PUT /specializations/{spec} | UpdateSpecializationRequest | spec->update() | Redirect back | super_admin, dept_manager |
| destroy() | DELETE /specializations/{spec} | Specialization | Blocks if spec has projects; spec->delete() | Redirect back | super_admin, dept_manager |

### app/Http/Controllers/ProjectController.php

**Purpose:** Full CRUD for projects with approval workflow and soft delete.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /projects | Request (filters) | SearchService::searchProjects() + getFilterOptions() | Inertia: Projects/Index | auth |
| create() | GET /projects/create | — | Passes depts, specializations, supervisors | Inertia: Projects/Create | dept_staff, dept_manager, super_admin |
| store() | POST /projects | StoreProjectRequest | PDF upload; status=archived for managers, proposal_submitted for staff; creates students + document; detectSimilarity flash | Redirect to show | auth (role check in method) |
| show() | GET /projects/{id} | id | Eager-loads all relations; increments visit_count; returns availableExaminers | Inertia: Projects/Show | auth |
| edit() | GET /projects/{id}/edit | id | authorizeEdit() check; passes form data | Inertia: Projects/Edit | auth (role check in method) |
| update() | PUT /projects/{id} | UpdateProjectRequest | authorizeEdit(); replaces PDF if provided; replaces students; detectSimilarity flash | Redirect to show | auth (role check in method) |
| destroy() | DELETE /projects/{id} | id | Only dept_manager/super_admin; sets is_deleted=true | Redirect to index | dept_manager, super_admin |
| approve() | POST /projects/{id}/approve | id | Sets current_status_id=1 (archived) | Redirect back | dept_manager, super_admin |

**Private method `authorizeEdit(Project $project)`:** super_admin may edit any; dept_manager may edit within own dept; dept_staff may only edit pending (status_id=2) projects in own dept; otherwise abort 403.

### app/Http/Controllers/SearchController.php

**Purpose:** Global search page and autocomplete suggestions endpoint.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /search | Request (filters) | SearchService::searchProjects(); eager-loads students | Inertia: Search/Index | auth |
| suggestions() | GET /search/suggestions | ?q=string | Returns up to 5 project titles matching query (min 2 chars), ordered by visit_count | JSON array | auth |

### app/Http/Controllers/ExaminerController.php

**Purpose:** CRUD for examiner records with optional department filter.

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

### app/Http/Controllers/EvaluationController.php

**Purpose:** Add evaluation notes per examiner and update a project's final score.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| store() | POST /projects/{id}/evaluation | int $projectId, StoreEvaluationRequest | project->evaluations()->create(validated) | Redirect back | super_admin, dept_manager |
| updateScore() | PATCH /projects/{id}/score | int $projectId, UpdateScoreRequest | project->update(['final_score' => ...]) | Redirect back | super_admin, dept_manager |

### app/Http/Controllers/ImportController.php

**Purpose:** 4-step bulk import wizard: template download, preview (dry run), actual import, ZIP PDF upload.

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET /import | — | Renders wizard page | Inertia: Import/Index | super_admin |
| downloadTemplate() | GET /import/template | — | Returns ProjectImportTemplate as XLSX | XLSX download | super_admin |
| preview() | POST /import/preview | file (xlsx, max 5120KB) | ProjectsImport(dryRun:true); flashes preview summary | Redirect back | super_admin |
| import() | POST /import/run | file (xlsx, max 5120KB) | ProjectsImport(dryRun:false); flashes import_summary | Redirect back | super_admin |
| uploadPdfs() | POST /import/pdfs | zip_file (zip, max 51200KB) | Extracts ZIP; matches PDF filenames to project_title; saves to public storage; flashes pdf_summary | Redirect back | super_admin |

### app/Http/Controllers/PublicController.php

**Purpose:** Public-facing pages (no authentication required).

| Method | HTTP + URL | Receives | Does | Returns | Roles |
|---|---|---|---|---|---|
| index() | GET / | — | Counts archived projects, depts, specs for landing page stats | Inertia: Welcome | Public |
| browse() | GET /browse | ?search, ?department_id, ?specialization_id, ?academic_year | Filters archived (status_id=1, not deleted); paginates 12 per page | Inertia: Public/Browse | Public |
| show() | GET /browse/{id} | int $id | Loads only archived non-deleted project; increments visit_count; fetches 3 related by specialization | Inertia: Public/Show | Public |

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
| GET | /projects | ProjectController@index | auth | All authenticated |
| POST | /projects | ProjectController@store | auth | All authenticated (role checked in controller) |
| GET | /projects/create | ProjectController@create | auth | All authenticated (role checked in controller) |
| GET | /projects/{project} | ProjectController@show | auth | All authenticated |
| GET | /projects/{project}/edit | ProjectController@edit | auth | All authenticated (role checked in controller) |
| PUT | /projects/{project} | ProjectController@update | auth | All authenticated (role checked in controller) |
| DELETE | /projects/{project} | ProjectController@destroy | auth | dept_manager, super_admin (checked in method) |
| POST | /projects/{id}/approve | ProjectController@approve | auth, role:dept_manager,super_admin | dept_manager, super_admin |
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

Auth routes (login, logout, password reset, email verification) are defined in routes/auth.php. Registration is disabled — POST /register redirects to /login with an error flash.

---

## 6. Services (app/Services/)

### app/Services/SearchService.php

**Purpose:** Encapsulates all project search, filter, and similarity detection logic.

| Method | Parameters | Return | Description |
|---|---|---|---|
| searchProjects(array $filters) | filters: search, department_id, specialization_id, academic_year, supervisor_id, status, sort | LengthAwarePaginator (15 per page) | Builds query; applies all filters; eager-loads department/specialization/supervisor/currentStatus; withCount('students'); sort options: title, visit_count, created_at (default) |
| detectSimilarity(string $title, ?int $excludeId) | title string, optional excludeId to skip on edit | Collection of up to 5 Projects | LIKE query on project_title; excludes current project when editing; returns id, project_title, academic_year, department_id with department relation |
| getFilterOptions() | — | array | Returns departments (with nested specializations), academic_years (distinct ordered desc), supervisors (role=supervisor) |

### app/Services/ReportService.php

**Purpose:** Generates all statistical data for reports and dashboard.

| Method | Parameters | Return | Description |
|---|---|---|---|
| getDashboardStats() | — | array | total_projects, total_departments, projects_this_year (current year LIKE), pending_approvals (status_id=2), recent_projects (last 5 with dept/spec/status), by_status (grouped counts joined with project_status) |
| getDepartmentReport(?int $departmentId) | Optional dept filter | array with keys: departments, supervisors | Per-dept: project_count, avg_score, scored_count, specializations breakdown; supervisors with project_count and department |
| getSpecializationTrends() | — | array with keys: top_specializations, rare_specializations, by_year | Top 10 by project count; bottom 5 (rare); grouped by_year data |
| getSupervisorReport() | — | array | All supervisors with project_count, avg_score, scored_count, by_year breakdown (grouped per supervisor) |
| getYearlyComparisonReport() | — | array with keys: yearly, department_by_year | Per-year count + growth % calculation; dept×year matrix grouping |

---

## 7. Vue Pages (resources/js/Pages/)

### resources/js/Pages/Welcome.vue

- **Purpose:** Arabic landing page with hero section, stats row, feature grid, and CTA buttons
- **URL:** GET /
- **Props:** `stats: { total_projects, total_departments, total_specializations }`
- **Interactions:** "تصفح المشاريع" button links to /browse; "تسجيل الدخول" links to /login
- **Roles:** Public (no auth)

### resources/js/Pages/Dashboard.vue

- **Purpose:** Role-based dashboard with stats cards, recent projects table, status bar chart, and report quick-links
- **URL:** GET /dashboard
- **Props:** `stats: DashboardStats` (structure varies by role)
- **Interactions:**
  - super_admin: 4 StatsCard components + recent projects table + status bar chart + 4 report quick-links
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

### resources/js/Pages/Projects/Index.vue

- **Purpose:** Paginated project list with search, advanced filters, and active filter chips
- **URL:** GET /projects
- **Props:** `projects: PaginatedProjects`, `filterOptions: FilterOptions`, `filters: object`
- **Interactions:** SearchBar with debounced autocomplete; FilterPanel with cascaded dept/spec/year/supervisor/sort; active filter chips (clickable x to remove); pagination; role-based Edit/Delete/Approve buttons per row; SimilarityWarning banner from flash
- **Roles:** All authenticated (role-based button visibility)

### resources/js/Pages/Projects/Create.vue

- **Purpose:** Form to create a new project
- **URL:** GET /projects/create
- **Props:** `departments`, `specializations`, `supervisors`
- **Interactions:** Title, description, academic_year inputs; cascaded department/specialization select; dynamic students add/remove rows; PDF file upload with progress bar; uses forceFormData for nested arrays and file upload
- **Roles:** dept_staff, dept_manager, super_admin

### resources/js/Pages/Projects/Edit.vue

- **Purpose:** Form to edit an existing project
- **URL:** GET /projects/{id}/edit
- **Props:** `project` (with students and documents), `departments`, `specializations`, `supervisors`
- **Interactions:** Pre-filled form; current PDF shown with download link; option to replace PDF; uses form.put() with forceFormData
- **Roles:** super_admin (any), dept_manager (own dept), dept_staff (pending + own dept only)

### resources/js/Pages/Projects/Show.vue

- **Purpose:** Full project detail page with 2-column layout
- **URL:** GET /projects/{id}
- **Props:** `project: Project` (with all relations eager-loaded), `availableExaminers: Examiner[]`
- **Interactions:** Main column: description, students table, examiners/evaluations section with assign/remove; Sidebar: project meta info, ScoreInput (managers) or read-only score display + pass/fail badge, PDF download; Approve/Edit/Delete role-based header buttons; AssignExaminerModal and ConfirmDelete dialogs
- **Roles:** All authenticated

### resources/js/Pages/Search/Index.vue

- **Purpose:** Global search page with results grouped by department
- **URL:** GET /search
- **Props:** `projects: PaginatedProjects`, `filterOptions`, `filters`
- **Interactions:** SearchBar with live suggestions; project cards showing highlighted match text (v-html); student name tags; pagination
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
- **Props:** `projects: Paginated`, `departments`, `specializations`, `years`, `filters`
- **Interactions:** Header with college logo and login button; search input + dept/spec/year filter selects; Apply/Reset buttons; 3-column project card grid; pagination; empty state with reset button
- **Roles:** Public (no auth required)

### resources/js/Pages/Public/Show.vue

- **Purpose:** Public project detail page — no authentication required
- **URL:** GET /browse/{id}
- **Props:** `project: Project` (with all relations), `related: Project[]`
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
- **Used in:** Projects/Index.vue, Search/Index.vue

#### resources/js/components/FilterPanel.vue

- **Purpose:** Collapsible advanced filter panel for project listings; also exports FilterValues type
- **Props:** departments, specializations, years, supervisors, modelValue (FilterValues)
- **Events emitted:** @filter-changed (emits FilterValues)
- **Used in:** Projects/Index.vue

#### resources/js/components/SimilarityWarning.vue

- **Purpose:** Warning banner displayed when a new/edited project title matches existing project titles
- **Props:** show (boolean), similarProjects (SimilarProject[])
- **Events emitted:** @continue, @change-title
- **Used in:** Projects/Index.vue (driven by flash.similarity_warning from server)

#### resources/js/components/Modal.vue

- **Purpose:** Generic dialog/modal with title prop, default body slot, and named #footer slot
- **Props:** show (boolean), title (string)
- **Events emitted:** @close
- **Used in:** Departments/Index.vue, Admin/Users/Index.vue

#### resources/js/components/ConfirmDelete.vue

- **Purpose:** Confirmation dialog before deleting an item; shows item name
- **Props:** show (boolean), itemName (string | undefined)
- **Events emitted:** @confirmed, @cancelled
- **Used in:** Projects/Index.vue, Projects/Show.vue, Departments/Index.vue, Examiners/Index.vue, Admin/Users/Index.vue

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

**Run order:**
1. RoleSeeder
2. ProjectStatusSeeder
3. AdminSeeder
4. DummyDataSeeder

### database/seeders/RoleSeeder.php

- **Creates:** 5 Spatie roles via `Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'])`
  - super_admin, dept_manager, supervisor, dept_staff, viewer
- **Command:** `php artisan db:seed --class=RoleSeeder`

### database/seeders/ProjectStatusSeeder.php

- **Creates:** 10 project status records via `ProjectStatus::updateOrCreate()` using fixed IDs
- See Section 2 (project_status table) for exact values with sort_order and is_active
- **Command:** `php artisan db:seed --class=ProjectStatusSeeder`

### database/seeders/AdminSeeder.php

- **Creates:** 1 admin user
  - email: admin@admin.com
  - name: Admin
  - password: password (hashed via Hash::make)
  - is_active: true
  - role: super_admin (via assignRole)
- **Command:** `php artisan db:seed --class=AdminSeeder`

### database/seeders/DummyDataSeeder.php

Runs everything inside a DB transaction for consistency.

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

- **Projects (25 total):** Various statuses (1=archived, 2=proposal_submitted, 5=in_progress, 6=ready_for_defense); 2-4 students per project; scored projects get 2 assigned examiners + 2 evaluations; 3 projects have based_on_project_id set (evolution chains)

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
- **9 test methods:**
  - Unauthenticated user redirected to /login from /dashboard
  - super_admin can access dashboard
  - super_admin can access admin panel (/admin)
  - dept_staff cannot access admin panel (403)
  - dept_manager cannot access admin panel (403)
  - dept_manager can access /departments
  - viewer cannot access /departments (403)
  - dept_staff can access /projects/create
  - viewer cannot access /projects/create (403)
- **Run:** `php artisan test tests/Feature/Auth/RBACTest.php`

### tests/Feature/Roles/RoleVerificationTest.php

- **Purpose:** Comprehensive RBAC verification across all 5 roles
- **58 test methods, 80 assertions:**
  - super_admin (15 tests): full system access, can delete empty dept, blocked on dept with projects
  - dept_manager (15 tests): cannot create/delete depts, can update own dept, blocked on others dept, project lifecycle, examiners, score, cannot import
  - supervisor (11 tests): read-only on projects/dashboard, all write operations return 403
  - dept_staff (13 tests): create/edit pending projects in own dept, blocked cross-dept, blocked post-approval
  - viewer (6 tests): dashboard only, all manage/report/write routes return 403
- **Run:** `php artisan test tests/Feature/Roles/RoleVerificationTest.php`

### tests/Feature/DashboardTest.php

- Tests dashboard accessibility for authenticated users

### tests/Feature/Models/UserModelTest.php

- Tests User model: fillable fields, casts, relationships (~5 tests)

### tests/Feature/Models/ProjectModelTest.php

- Tests Project model: fillable fields, relationships (~5 tests)

### tests/Feature/Seeders/RoleSeederTest.php

- Verifies all 5 roles exist after seeding

### tests/Feature/Seeders/ProjectStatusSeederTest.php

- Verifies all 10 project statuses exist after seeding

### tests/Feature/Department/DepartmentTest.php

- **Purpose:** Department CRUD, access control, validation, specialization management
- **14 test methods** covering super_admin full CRUD, dept_manager read/update own dept only, dept_staff blocked, spec create/update/destroy, project-link guard on delete
- **Run:** `php artisan test tests/Feature/Department/DepartmentTest.php`

### tests/Feature/Project/ProjectTest.php

- **Purpose:** Full project lifecycle: create, store with status, approve, soft delete, visit count, search/filter, similarity detection
- **14 test methods:**
  - super_admin can view all projects
  - dept_manager creates project (status=archived)
  - dept_staff creates project (status=proposal_submitted)
  - dept_staff cannot create project in other dept (403)
  - Non-PDF file rejected (validation error)
  - PDF > 15MB rejected (validation error)
  - dept_manager can approve pending project
  - dept_staff cannot approve project (403)
  - dept_manager can soft delete project (is_deleted=true)
  - dept_staff cannot delete project (403)
  - Visit count increments on each show() call
  - Search by title returns only matching projects
  - Filter by department returns only that dept's projects
  - Filter by academic year returns correct projects
  - Duplicate title projects both appear in search
- **Run:** `php artisan test tests/Feature/Project/ProjectTest.php`

### tests/Feature/Search/SearchTest.php

- **Purpose:** SearchService and SearchController functionality
- **15 test methods, 150 assertions** covering search by title, description, case insensitivity, all individual filters, combined filters, empty search, pagination, similarity detection (finds matches, ignores current project on edit), suggestions max 5, suggestions match only, unauthenticated blocked
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

- **Purpose:** Bulk import controller and import logic
- **15 test methods, 52 assertions:**
  - Access: super_admin allowed, dept_manager gets 403
  - Template download returns XLSX
  - Non-excel file rejected; file > 5MB rejected
  - Valid Excel row creates project with archived status
  - Per-row isolation: bad dept_code/spec/supervisor/missing title fails only that row
  - Students created correctly; empty student slots skipped
  - Summary counts are correct
  - dryRun:true touches 0 DB rows; dryRun:false writes 1 project
- **Note:** 10 failures occur in XAMPP environment (ext-zip disabled)
- **Run:** `php artisan test tests/Feature/Import/ImportTest.php`

### tests/Feature/Report/ReportTest.php

- **Purpose:** ReportController and ReportService
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

- **Purpose:** Public browse and show pages, auth redirect tests
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

- Profile update validation and success (~3 tests)

### tests/Feature/Settings/PasswordUpdateTest.php

- Password update flow (~3 tests)

**Total test suite: 208 passing / 218 total (10 Import failures due to ext-zip disabled in XAMPP)**

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
- approve() endpoint changes status to archived
- Soft delete via is_deleted=true (not deleted_at)
- PDF stored in storage/public/projects; ProjectDocument record created on upload
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
5. **Document milestone tracking** — `project_documents` table exists and records are created on upload; but there is no UI for browsing a project's document history or associating documents to specific lifecycle milestones
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
|   |   |-- ProjectDocument.php
|   |   |-- ProjectExaminer.php
|   |   |-- ProjectStatus.php
|   |   |-- ProjectStudent.php
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
