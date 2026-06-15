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
- ⏳ Next: Department & Specialization management (CRUD + UI)
- ⏳ Next: Project CRUD with PDF upload
- ⏳ Next: Project approval workflow (staff → manager)

## Do NOT
- Do not use deleted_at for soft delete (use is_deleted boolean)
- Do not create REST API endpoints (use Inertia responses)
- Do not store every draft (only milestone documents)
- Do not allow mobile layout (desktop only)
- Do not mix Arabic in code (English only in code, Arabic in UI text)
