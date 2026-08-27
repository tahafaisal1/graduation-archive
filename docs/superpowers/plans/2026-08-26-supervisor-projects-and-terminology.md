# Supervisor "مشاريعي" Route + Terminology + Button-Label Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix three confirmed, unreported issues surfaced by the read-only gap analysis: (1) the supervisor's "مشاريعي" sidebar link is dead (no route, no scoping logic), (2) Dashboard.vue and Search/Index.vue mislabel Proposal records as "مشروع/مشاريع" (Project), and (3) the proposal instantiate button and its confirm dialog inconsistently mix "تنزيل" (download) and "إنشاء" (create) language for the same action.

**Architecture:** Fix 1 adds one new GET route + one new thin controller method that reuses the existing `Projects/Index.vue` page (given two new optional props) rather than a duplicate Vue file — mirrors the existing `ProjectController::index()` query shape with an added `whereHas('proposal', ...)` supervisor scope, gated by `role:supervisor` route middleware (consistent with `RoleMiddleware`'s existing 403-on-mismatch behavior). Fixes 2 and 3 are pure Arabic-copy edits — no route, prop, or backend key is renamed, since the analysis confirmed those already resolve correctly; only the visible label was calling a `Proposal` a "Project" or calling `instantiateProject()` a "download." Fix 2's scope was expanded beyond the two files the gap-analysis doc named (Dashboard.vue, Search/Index.vue) after discovering, during planning, that all four Reports pages (`Reports/Department.vue`, `Specializations.vue`, `Supervisors.vue`, `Yearly.vue`) have the identical leak — `ReportService`'s `project_count`/count fields are almost entirely `Proposal`-backed (confirmed by reading `app/Services/ReportService.php`) except `avg_score`, which is genuinely `Project`-backed (joins `projects.final_score`) and is deliberately left worded as "مشاريع." Confirmed with the user before expanding.

**Tech Stack:** Laravel 12 (PHP 8.2), Vue 3 + Inertia 2, Pest PHP, Spatie `role:` route middleware (`app/Http/Middleware/RoleMiddleware.php`).

**Spec:** `docs/analysis/current-system-behavior.md` (branch `analysis-current-system-behavior`, commit `58e38a6`) — issues #2 (Fix 1), #3 (Fix 2), #4 (Fix 3). Base branch for this plan: `main` at `28e252b` (finalize-archive PR #1 is NOT merged and this plan does not depend on it — confirmed with the user).

## Global Constraints

- Base branch is `main` (`28e252b`), not `worktree-project-finalize-archive` — keep this branch independent of that unmerged PR.
- No renaming of backend prop keys, TS interface names, or route names for Fix 2 — only visible Arabic copy changes (the analysis confirmed links/routes are already correct; only labels are wrong). The one exception is Search/Index.vue's local `project` loop variable (see Task 3), which the brief calls out explicitly for code clarity, not correctness.
- `projects/my` MUST be registered before `projects/{id}` in `routes/web.php` — the existing `projects/{id}` route has no numeric constraint, so route order is the only thing preventing `/projects/my` from matching `{id}="my"` first.
- Reuse `Projects/Index.vue` for the supervisor's "my projects" view via optional props — do not create a new Vue page for this.
- `php artisan test` must stay at 232/232 (0 failures) after every task; `npm run build` must stay clean.
- Full suite baseline (already verified in this worktree before writing this plan): 232 passed, 823 assertions, 0 failures.

---

## File Structure

| File | Change |
|---|---|
| `routes/web.php` | Modify — insert `GET projects/my` (named `projects.my`, `role:supervisor` middleware) before `GET projects/{id}` |
| `app/Http/Controllers/ProjectController.php` | Modify — add `myProjects(Request $request): Response` |
| `resources/js/pages/Projects/Index.vue` | Modify — add optional `heading` / `breadcrumbHref` props (default to current behavior) |
| `tests/Feature/Project/MyProjectsTest.php` | Create — 3 new tests for Fix 1 |
| `resources/js/pages/Dashboard.vue` | Modify — 8 label edits (proposal-vs-project terminology) |
| `resources/js/pages/Search/Index.vue` | Modify — 4 label edits + rename local loop variable `project` → `proposal` in the department-grouped results block |
| `resources/js/pages/Reports/Department.vue` | Modify — 6 label edits (proposal-vs-project terminology; `avg_score` sub-label untouched) |
| `resources/js/pages/Reports/Specializations.vue` | Modify — 4 label edits |
| `resources/js/pages/Reports/Supervisors.vue` | Modify — 3 label edits |
| `resources/js/pages/Reports/Yearly.vue` | Modify — 3 label edits |
| `resources/js/pages/Proposals/Show.vue` | Modify — 2 label edits (button + dialog confirm-label) |

---

## Task 1: Failing tests for the supervisor-scoped `projects.my` route

**Files:**
- Create: `tests/Feature/Project/MyProjectsTest.php`

**Interfaces:**
- Consumes: `userWithRole(string $role, array $attributes = []): User` (global Pest helper, `tests/Pest.php:44`), `Proposal::factory()`, `Proposal::instantiateProject(User $actor): Project` (`app/Models/Proposal.php`), `Proposal::STATUS_ARCHIVED` constant, `RoleSeeder`, `ProjectStatusSeeder`, `ProjectLifecycleStatusSeeder` (all seeded in `beforeEach`, matching the pattern in `tests/Feature/Project/ProjectTest.php:25-30`).
- Produces: nothing consumed by later tasks — this is the leaf test file for Fix 1.

- [ ] **Step 1: Write the 3 failing tests**

```php
<?php
// tests/Feature/Project/MyProjectsTest.php
//
// Covers the "مشاريعي" (My Projects) supervisor route — confirmed broken
// by docs/analysis/current-system-behavior.md §2: the sidebar link pointed
// at /projects/my with no route, no controller method, and no
// supervisor-scoping query anywhere.

use App\Models\Department;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\ProjectLifecycleStatusSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
    $this->seed(ProjectLifecycleStatusSeeder::class);
});

/** Creates an instantiated (مؤرشف proposal + قيد التنفيذ project) row supervised by $supervisor. */
function makeSupervisedProject(User $supervisor, ?Department $dept = null, ?Specialization $spec = null): Project
{
    $dept ??= Department::factory()->create();
    $spec ??= Specialization::factory()->create(['department_id' => $dept->id]);

    $proposal = Proposal::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
        'is_deleted'        => false,
    ]);

    return $proposal->instantiateProject($supervisor);
}

test('supervisor viewing my-projects sees only projects they supervise', function () {
    $supervisorA = userWithRole('supervisor');
    $supervisorB = userWithRole('supervisor');

    $ownProject   = makeSupervisedProject($supervisorA);
    $otherProject = makeSupervisedProject($supervisorB);

    $this->actingAs($supervisorA)
        ->get(route('projects.my'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $ownProject->id)
        );

    expect($otherProject->id)->not->toBe($ownProject->id);
});

test('non-supervisor roles are forbidden from my-projects route', function (string $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('projects.my'))
        ->assertForbidden();
})->with(['super_admin', 'dept_manager', 'dept_staff', 'viewer']);

test('my-projects route renders Projects/Index with مشاريعي heading and correct data', function () {
    $supervisor = userWithRole('supervisor');
    $project    = makeSupervisedProject($supervisor);

    $this->actingAs($supervisor)
        ->get(route('projects.my'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->where('heading', 'مشاريعي')
            ->has('projects.data', 1)
            ->where('projects.data.0.proposal.supervisor.id', $supervisor->id)
        );
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=MyProjectsTest`
Expected: FAIL — `route('projects.my')` throws `RouteNotFoundException` (route doesn't exist yet) for all 3 tests (the parameterized one fails once per dataset row).

- [ ] **Step 3: Commit the failing test**

```bash
git add tests/Feature/Project/MyProjectsTest.php
git commit -m "test: add failing tests for supervisor-scoped projects.my route"
```

---

## Task 2: Add the `projects.my` route + `ProjectController::myProjects()`

**Files:**
- Modify: `routes/web.php:85-86`
- Modify: `app/Http/Controllers/ProjectController.php`

**Interfaces:**
- Consumes: `Project` model with its existing `$with = ['proposal.supervisor', 'proposal.students']` default (`app/Models/Project.php:41`) and `proposal()` BelongsTo relation; `Proposal.supervisor_id` column.
- Produces: named route `projects.my` (GET, `role:supervisor` middleware) and `ProjectController::myProjects(Request $request): Response`, rendering `Projects/Index` with props `projects` (paginated, same shape as `index()`) and `heading` (string).

- [ ] **Step 1: Add the route before `projects/{id}`**

In `routes/web.php`, inside the existing `Route::middleware(['auth'])->group(function () { ... })` block (currently lines 80-90), change:

```php
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{id}', [ProjectController::class, 'show'])->name('projects.show');
```

to:

```php
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/my', [ProjectController::class, 'myProjects'])
        ->middleware('role:supervisor')
        ->name('projects.my');
    Route::get('projects/{id}', [ProjectController::class, 'show'])->name('projects.show');
```

`projects/my` must stay above `projects/{id}` — Laravel matches routes in registration order and `{id}` has no `whereNumber()` constraint, so `my` would otherwise bind to `$id` as a literal string.

- [ ] **Step 2: Add `myProjects()` to `ProjectController`**

In `app/Http/Controllers/ProjectController.php`, add this method (after `index()`, before `show()`):

```php
    public function myProjects(Request $request): Response
    {
        $projects = Project::whereHas('proposal', fn ($q) => $q->where('supervisor_id', $request->user()->id))
            ->with(['proposal.department', 'status'])
            ->where('is_deleted', false)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'heading'  => 'مشاريعي',
        ]);
    }
```

- [ ] **Step 3: Run the Task 1 tests to verify they pass**

Run: `php artisan test --filter=MyProjectsTest`
Expected: the "sees only projects they supervise" and "forbidden" tests PASS. The third test (heading prop) will still FAIL until Task 3 adds the `heading` prop to the Vue page — Inertia's `assertInertia` only inspects the props actually sent from the backend, so `where('heading', 'مشاريعي')` already passes at this step (the prop is being sent); it's the Vue rendering that Task 3 covers. Confirm all 3 pass here — if the third fails, re-check the controller's `'heading' => 'مشاريعي'` key spelling before moving on.

- [ ] **Step 4: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 235/235 passed (232 baseline + 3 new), 0 failures.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php app/Http/Controllers/ProjectController.php
git commit -m "feat: add supervisor-scoped projects.my route and controller method"
```

---

## Task 3: Reuse `Projects/Index.vue` for the supervisor view (heading prop)

**Files:**
- Modify: `resources/js/pages/Projects/Index.vue`

**Interfaces:**
- Consumes: `heading` prop (string, optional, sent by `ProjectController::myProjects()` from Task 2; `ProjectController::index()` sends no such prop, so a default is required).
- Produces: nothing consumed elsewhere — leaf UI change.

- [ ] **Step 1: Add the optional `heading` prop with a default matching current behavior**

In `resources/js/pages/Projects/Index.vue`, change:

```ts
defineProps<{ projects: PaginatedProjects }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'المشاريع', href: '/projects' },
];
```

to:

```ts
const props = withDefaults(defineProps<{ projects: PaginatedProjects; heading?: string }>(), {
    heading: 'المشاريع',
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: props.heading, href: props.heading === 'مشاريعي' ? '/projects/my' : '/projects' },
]);
```

Add `computed` to the existing `import { Head, router } from '@inertiajs/vue3';` line's neighboring import — the file has no Vue import yet, so add a new line: `import { computed } from 'vue';` directly below the Inertia import.

- [ ] **Step 2: Wire the prop into the template**

Change:

```html
    <Head title="المشاريع" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4" dir="rtl">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">المشاريع</h1>
```

to:

```html
    <Head :title="heading" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4" dir="rtl">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ heading }}</h1>
```

- [ ] **Step 3: Run the Task 1 tests to verify all 3 now pass**

Run: `php artisan test --filter=MyProjectsTest`
Expected: PASS (3/3).

- [ ] **Step 4: Build the frontend and run the full suite**

Run: `npm run build`
Expected: clean build, no TS/Vue errors.

Run: `php artisan test`
Expected: 235/235 passed, 0 failures.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/Projects/Index.vue
git commit -m "feat: reuse Projects/Index.vue for the supervisor my-projects view"
```

---

## Task 4: Fix 2 — correct Proposal-vs-Project terminology in Dashboard.vue

**Files:**
- Modify: `resources/js/pages/Dashboard.vue`

**Interfaces:**
- Consumes: nothing new — `ReportService::getDashboardStats()` (`app/Services/ReportService.php:13-41`) already returns `total_projects`/`projects_this_year`/`pending_approvals`/`recent_projects`/`by_status` all sourced from `Proposal::` queries, and `getDepartmentReport()` (`app/Services/ReportService.php:43-90`)'s `project_count` fields are `Department`/`Specialization`/`User`'s `proposals()` relation counts (aliased `as project_count` in the PHP, not renamed here — out of scope, see Global Constraints).
- Produces: nothing — leaf UI copy change. No prop/key names change, so no backend or type changes needed.

Do not touch: the `متوسط الدرجات` stat (`avg_score`, its `للمشاريع المقيَّمة` sub-label, line ~233) — this one is genuinely about instantiated `Project.final_score` (via `ReportService::avgScoresGroupedBy()` joining the `projects` table), so "مشاريع" is correct there. Do not touch the "عرض المشاريع" link to `/projects` (line ~334) — that is the real Projects index, correctly labeled.

- [ ] **Step 1: Apply the 8 label edits**

| Line (pre-edit) | Old text | New text |
|---|---|---|
| ~94 | `title="إجمالي المشاريع"` (super_admin, `total_projects`) | `title="إجمالي المقترحات"` |
| ~106 | `title="مشاريع هذا العام"` (`projects_this_year`) | `title="مقترحات هذا العام"` |
| ~112 | `title="مشاريع مقترحة"` (`pending_approvals`) | `title="مقترحات قيد الانتظار"` |
| ~125 | `<h3 ...>آخر المشاريع المضافة</h3>` | `<h3 ...>آخر المقترحات المضافة</h3>` |
| ~131 | `<th ...>عنوان المشروع</th>` | `<th ...>عنوان المقترح</th>` |
| ~164 | `<td colspan="4" ...>لا توجد مشاريع</td>` | `<td colspan="4" ...>لا توجد مقترحات</td>` |
| ~174 | `<h3 ...>المشاريع حسب الحالة</h3>` | `<h3 ...>المقترحات حسب الحالة</h3>` |
| ~223 | `title="مشاريع القسم"` (dept_manager, `project_count`) | `title="مقترحات القسم"` |
| ~256 | `<th ...>عدد المشاريع</th>` (specializations table) | `<th ...>عدد المقترحات</th>` |
| ~289 | `{{ sup.project_count }} مشروع` (supervisors list) | `{{ sup.project_count }} مقترح` |
| ~324 | `title="إجمالي المشاريع"` (default role, `total_projects`) | `title="إجمالي المقترحات"` |

Use exact string matches — there are two identical `title="إجمالي المشاريع"` occurrences (super_admin block ~line 94, default block ~line 324); both must change, and both are unambiguous from surrounding context (super_admin `<template>` vs the final `<template v-else>`).

- [ ] **Step 2: Grep to confirm no stray "مشروع"/"مشاريع" remains where it means a Proposal**

Run: `grep -n "مشروع\|مشاريع" resources/js/pages/Dashboard.vue`
Expected remaining matches: only the `متوسط الدرجات` sub-label (`للمشاريع المقيَّمة`) and the `عرض المشاريع` / `href="/projects"` link in the default-role block — both intentionally left as "مشاريع" per the "Do not touch" note above.

- [ ] **Step 3: Build and run the full suite**

Run: `npm run build`
Expected: clean build.

Run: `php artisan test`
Expected: 235/235 passed, 0 failures (no backend test asserts this Arabic copy — confirmed via `grep -rn "آخر المشاريع المضافة\|مشاريع مقترحة\|عنوان المشروع" tests/` returning nothing before this task started).

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Dashboard.vue
git commit -m "fix: correct proposal-vs-project terminology on the dashboard"
```

---

## Task 5: Fix 2 — correct terminology in Search/Index.vue + rename the misleading loop variable

**Files:**
- Modify: `resources/js/pages/Search/Index.vue`

**Interfaces:**
- Consumes: nothing new — `SearchController::index()` already searches `Proposal` rows via `SearchService::searchProposals()` and links to `route('proposals.show', ...)` (already correct, per the analysis §1a route-mapping sweep). The `projects` prop name and `Project` TS interface name are NOT renamed (out of scope per Global Constraints) — only the copy and the local loop variable at line ~187-228.
- Produces: nothing — leaf UI copy change.

- [ ] **Step 1: Apply the 4 visible-copy edits**

| Line (pre-edit) | Old text | New text |
|---|---|---|
| ~137 | `<h1 ...>البحث في المشاريع</h1>` | `<h1 ...>البحث في المقترحات</h1>` |
| ~140 | `placeholder="ابحث عن مشروع بالعنوان أو الوصف..."` | `placeholder="ابحث عن مقترح بالعنوان أو الوصف..."` |
| ~159 | `<p ...>لم يتم العثور على مشاريع تطابق "{{ searchQuery }}"</p>` | `<p ...>لم يتم العثور على مقترحات تطابق "{{ searchQuery }}"</p>` |
| ~164 | `<p ...>اكتب كلمة بحث للعثور على المشاريع</p>` | `<p ...>اكتب كلمة بحث للعثور على المقترحات</p>` |

- [ ] **Step 2: Rename the `project` loop variable to `proposal` in the results block**

The loop variable introduced at line ~187 (`v-for="project in deptProjects"`) is a `Proposal` row (per the analysis: `deptProjects` groups the `projects` prop, which `SearchController::searchProposals()` populates with `Proposal` records) — rename every reference to it within that `<a>` block only (do not touch `deptProjects`, the `projects` prop, or the `Project` TS interface — those stay as-is per Global Constraints). Change:

```html
                        <a
                            v-for="project in deptProjects"
                            :key="project.id"
                            :href="route('proposals.show', [project.id])"
                            class="block rounded-lg border border-gray-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-700"
                        >
                            <!-- Title with highlight -->
                            <p
                                class="text-sm font-semibold text-blue-600 dark:text-blue-400"
                                v-html="highlight(project.title)"
                            />

                            <!-- Meta row -->
                            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span v-if="project.specialization">
                                    📚 {{ project.specialization.name }}
                                </span>
                                <span v-if="project.academic_year">
                                    📅 {{ project.academic_year }}
                                </span>
                                <span v-if="project.supervisor">
                                    👤 {{ project.supervisor.name }}
                                </span>
                            </div>

                            <!-- Students -->
                            <div v-if="project.students && project.students.length > 0" class="mt-1.5 flex flex-wrap gap-1">
                                <span
                                    v-for="student in project.students"
                                    :key="student.id"
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                >
                                    {{ student.full_name }}
                                </span>
                            </div>

                            <!-- Description snippet with highlight -->
                            <p
                                v-if="project.description"
                                class="mt-2 line-clamp-2 text-xs text-gray-500 dark:text-gray-400"
                                v-html="highlight(project.description)"
                            />
                        </a>
```

to (every `project.` → `proposal.`, the `v-for` binding renamed, `key` and `href` updated too):

```html
                        <a
                            v-for="proposal in deptProjects"
                            :key="proposal.id"
                            :href="route('proposals.show', [proposal.id])"
                            class="block rounded-lg border border-gray-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-700"
                        >
                            <!-- Title with highlight -->
                            <p
                                class="text-sm font-semibold text-blue-600 dark:text-blue-400"
                                v-html="highlight(proposal.title)"
                            />

                            <!-- Meta row -->
                            <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                <span v-if="proposal.specialization">
                                    📚 {{ proposal.specialization.name }}
                                </span>
                                <span v-if="proposal.academic_year">
                                    📅 {{ proposal.academic_year }}
                                </span>
                                <span v-if="proposal.supervisor">
                                    👤 {{ proposal.supervisor.name }}
                                </span>
                            </div>

                            <!-- Students -->
                            <div v-if="proposal.students && proposal.students.length > 0" class="mt-1.5 flex flex-wrap gap-1">
                                <span
                                    v-for="student in proposal.students"
                                    :key="student.id"
                                    class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400"
                                >
                                    {{ student.full_name }}
                                </span>
                            </div>

                            <!-- Description snippet with highlight -->
                            <p
                                v-if="proposal.description"
                                class="mt-2 line-clamp-2 text-xs text-gray-500 dark:text-gray-400"
                                v-html="highlight(proposal.description)"
                            />
                        </a>
```

- [ ] **Step 3: Build and run the full suite**

Run: `npm run build`
Expected: clean build (renaming a `v-for` local variable is not a type-breaking change since `Project` interface is untouched).

Run: `php artisan test`
Expected: 235/235 passed, 0 failures.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Search/Index.vue
git commit -m "fix: correct proposal-vs-project terminology and loop variable naming on the search page"
```

---

## Task 6: Fix 2 (expanded) — correct terminology across all 4 Reports pages

**Files:**
- Modify: `resources/js/pages/Reports/Department.vue`
- Modify: `resources/js/pages/Reports/Specializations.vue`
- Modify: `resources/js/pages/Reports/Supervisors.vue`
- Modify: `resources/js/pages/Reports/Yearly.vue`

**Interfaces:**
- Consumes: nothing new — `ReportService::getDepartmentReport()`, `getSpecializationTrends()`, `getSupervisorReport()`, `getYearlyComparisonReport()` (`app/Services/ReportService.php:43-198`) all source their count fields from `Proposal`/`proposals()` relations; only `avg_score`/`scored_count` (via `avgScoresGroupedBy()`, `app/Services/ReportService.php:205-216`) are genuinely `Project`-backed.
- Produces: nothing — leaf UI copy change. No prop/key names change (`project_count` stays `project_count` in the TS interfaces and PHP arrays — out of scope per Global Constraints, same rule as Task 4).

Do not touch: `Reports/Department.vue`'s `sub="للمشاريع المقيَّمة"` (line ~145, under the "متوسط الدرجات" avg-score stat) — genuinely Project-based, correctly worded.

- [ ] **Step 1: `Reports/Department.vue` — 6 edits**

| Line (pre-edit) | Old text | New text |
|---|---|---|
| ~92 | `إحصائيات المشاريع حسب الأقسام والتخصصات` | `إحصائيات المقترحات حسب الأقسام والتخصصات` |
| ~129 | `title="إجمالي المشاريع"` | `title="إجمالي المقترحات"` |
| ~160 | `<th ...>المشاريع</th>` (departments table) | `<th ...>المقترحات</th>` |
| ~205 | `<th ...>عدد المشاريع</th>` (specializations breakdown) | `<th ...>عدد المقترحات</th>` |
| ~242 | `<h2 ...>المشرفون وعدد مشاريعهم</h2>` | `<h2 ...>المشرفون وعدد مقترحاتهم</h2>` |
| ~250 | `<th ...>عدد المشاريع</th>` (supervisors table) | `<th ...>عدد المقترحات</th>` |

Leave line ~145 (`sub="للمشاريع المقيَّمة"`) unchanged.

- [ ] **Step 2: `Reports/Specializations.vue` — 4 edits**

| Line (pre-edit) | Old text | New text |
|---|---|---|
| ~84 | `توزيع المشاريع على التخصصات والاتجاهات السنوية` | `توزيع المقترحات على التخصصات والاتجاهات السنوية` |
| ~128 | `<h2 ...>الاتجاه السنوي — إجمالي مشاريع التخصصات</h2>` | `<h2 ...>الاتجاه السنوي — إجمالي مقترحات التخصصات</h2>` |
| ~138 | `إجمالي المشاريع` (year-trend table header) | `إجمالي المقترحات` |
| ~182 | `<th ...>عدد المشاريع</th>` (rare specializations) | `<th ...>عدد المقترحات</th>` |

- [ ] **Step 3: `Reports/Supervisors.vue` — 3 edits**

| Line (pre-edit) | Old text | New text |
|---|---|---|
| ~107 | `إحصائيات المشرفين وعدد مشاريعهم ومتوسط درجاتهم` | `إحصائيات المشرفين وعدد مقترحاتهم ومتوسط درجاتهم` |
| ~162 | `عدد المشاريع <ArrowUpDown :size="12" />` (sortable column) | `عدد المقترحات <ArrowUpDown :size="12" />` |
| ~175 | `<th ...>المشاريع بالسنة</th>` | `<th ...>المقترحات بالسنة</th>` |

- [ ] **Step 4: `Reports/Yearly.vue` — 3 edits**

| Line (pre-edit) | Old text | New text |
|---|---|---|
| ~99 | `مقارنة المشاريع عبر السنوات الأكاديمية` | `مقارنة المقترحات عبر السنوات الأكاديمية` |
| ~107 | `title="إجمالي المشاريع"` | `title="إجمالي المقترحات"` |
| ~134 | `<th ...>عدد المشاريع</th>` | `<th ...>عدد المقترحات</th>` |

- [ ] **Step 5: Grep to confirm only the intended survivor remains**

Run: `grep -rn "مشروع\|مشاريع" resources/js/pages/Reports/`
Expected remaining match: only `Reports/Department.vue`'s `sub="للمشاريع المقيَّمة"` (line ~145).

- [ ] **Step 6: Build and run the full suite**

Run: `npm run build`
Expected: clean build.

Run: `php artisan test`
Expected: 235/235 passed, 0 failures (confirmed via `grep -rn` against `tests/` before starting that no test asserts this Arabic copy).

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/Reports/Department.vue resources/js/pages/Reports/Specializations.vue resources/js/pages/Reports/Supervisors.vue resources/js/pages/Reports/Yearly.vue
git commit -m "fix: correct proposal-vs-project terminology across all Reports pages"
```

---

## Task 8: Fix 3 — align "تنزيل المشروع" button and dialog copy on "إنشاء"

**Files:**
- Modify: `resources/js/pages/Proposals/Show.vue`

**Interfaces:**
- Consumes: nothing new — `instantiateProject()` (line ~78) and `showConfirmInstantiate` (line ~75) are unchanged; only their associated visible labels change.
- Produces: nothing — leaf UI copy change.

The dialog's `title` prop (line ~319, `"تأكيد إنشاء المشروع"`) and `message` prop (line ~320, `"سيتم أرشفة المقترح وإنشاء مشروع جديد مرتبط به. لا يمكن التراجع عن هذا الإجراء. هل أنت متأكد؟"`) are already correct per the brief's target wording — verified against the current file, no edit needed there.

- [ ] **Step 1: Rename the trigger button label (line ~173)**

Change:

```html
                    <button
                        v-else-if="canInstantiate"
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="showConfirmInstantiate = true"
                    >
                        تنزيل المشروع
                    </button>
```

to:

```html
                    <button
                        v-else-if="canInstantiate"
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="showConfirmInstantiate = true"
                    >
                        إنشاء المشروع
                    </button>
```

(Only the inner text changes — confirm the `v-else-if`/class/click-handler on the actual file match before editing; use the exact surrounding lines as the match anchor since `تنزيل المشروع` alone is not guaranteed unique against the confirm-dialog's `confirm-label` in Step 2.)

- [ ] **Step 2: Fix the confirm-dialog's `confirm-label` (line ~321)**

Change:

```html
        <ConfirmDelete
            :show="showConfirmInstantiate"
            title="تأكيد إنشاء المشروع"
            message="سيتم أرشفة المقترح وإنشاء مشروع جديد مرتبط به. لا يمكن التراجع عن هذا الإجراء. هل أنت متأكد؟"
            confirm-label="تنزيل المشروع"
            confirm-color="green"
            @confirmed="instantiateProject"
            @cancelled="showConfirmInstantiate = false"
        />
```

to:

```html
        <ConfirmDelete
            :show="showConfirmInstantiate"
            title="تأكيد إنشاء المشروع"
            message="سيتم أرشفة المقترح وإنشاء مشروع جديد مرتبط به. لا يمكن التراجع عن هذا الإجراء. هل أنت متأكد؟"
            confirm-label="إنشاء المشروع"
            confirm-color="green"
            @confirmed="instantiateProject"
            @cancelled="showConfirmInstantiate = false"
        />
```

- [ ] **Step 3: Grep to confirm no remaining "تنزيل" in an instantiate context**

Run: `grep -n "تنزيل" resources/js/pages/Proposals/Show.vue`
Expected remaining match: only `↓ تنزيل الملف` (the actual PDF file download link, ~line 298) — that one is a real download and correctly labeled; do not change it.

- [ ] **Step 4: Build and run the full suite**

Run: `npm run build`
Expected: clean build.

Run: `php artisan test`
Expected: 235/235 passed, 0 failures.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/Proposals/Show.vue
git commit -m "fix: align instantiate-project button and dialog copy on \"إنشاء\" wording"
```

---

## Task 9: Update docs

**Files:**
- Modify: `CLAUDE.md`
- Modify: `PROGRESS.md`
- Modify: `docs/analysis/current-system-behavior.md` (this file lives on branch `analysis-current-system-behavior`; cherry-pick or manually port the same edit onto this branch's copy if the file isn't present here — check with `git show analysis-current-system-behavior:docs/analysis/current-system-behavior.md` first, since this branch was cut from `main`, not from `analysis-current-system-behavior`)

**Interfaces:**
- Consumes: the "Current Status" section format already established in `CLAUDE.md` (most recent entry is the Proposal/Project Split Task 8 review, see the entry starting `✅ **Proposal/Project Split — Task 8 final review complete**`).
- Produces: nothing — documentation only.

- [ ] **Step 1: Add a new "Current Status" entry to `CLAUDE.md`**

Prepend (immediately after the `## Current Status` heading, above the existing most-recent entry) a new bullet summarizing: `projects.my` route + supervisor scoping added; Dashboard/Search/Reports terminology corrected (proposal vs project); "تنزيل المشروع" renamed to "إنشاء المشروع" with dialog wording aligned; full test count (fill in the actual final count from the last suite run — 235 if no other tests were added).

- [ ] **Step 2: Add a matching "Bugfixes / Corrections" entry to `PROGRESS.md`**

Follow the existing entry format (see the "Ghost 'في انتظار الموافقة' status badge" entry in `PROGRESS.md` for the expected structure: root cause, what was checked, what changed).

- [ ] **Step 3: Mark issues #2, #3, #4 resolved in the analysis doc**

If `docs/analysis/current-system-behavior.md` exists on this branch (it won't, since this branch was cut from `main` before that file existed there — confirm with `git show analysis-current-system-behavior:docs/analysis/current-system-behavior.md | head -5` and `ls docs/analysis/ 2>/dev/null`), skip this step and note in the final report that the analysis doc lives only on its own branch and was not touched. Do not merge or cherry-pick unrelated commits from that branch just to reach the file.

- [ ] **Step 4: Commit**

```bash
git add CLAUDE.md PROGRESS.md
git commit -m "docs: record projects.my route, terminology, and instantiate-button fixes"
```

---

## Task 10: Full-branch review and verification

- [ ] **Step 1: Run `superpowers:verification-before-completion`** before claiming anything is done — re-run `php artisan test` and `npm run build` fresh, don't reuse earlier output.
- [ ] **Step 2: Run `/code-review` on the full branch diff** (`main...fix/supervisor-projects-and-terminology`), not per-task — the Proposal/Project Split's Task 8 review found 4 real issues that per-task review missed.
- [ ] **Step 3: Playwright walkthrough** (separate port, e.g. 8235, not 8000) covering:
  - Supervisor clicks "مشاريعي" → sees only their own projects; another supervisor's projects absent.
  - Non-supervisor (e.g. dept_staff) → "مشاريعي" sidebar item is hidden (already true structurally per `AppSidebar.vue`'s per-role switch, but verify visually).
  - super_admin → Dashboard + Search + all 4 Reports pages show "مقترح/مقترحات" labels, not "مشروع/مشاريع", for proposal-backed stats/copy.
  - dept_manager → on a مقترح Proposal Show page, confirm button reads "إنشاء المشروع" and the confirm dialog's title/message/confirm-button all agree on "إنشاء". Screenshot both.
- [ ] **Step 4: Re-verify issue #1** (sidebar-mismatch, confirmed non-existent in the analysis) — click through `/proposals` → a proposal → "عرض المشروع" once more on this branch to confirm nothing in Tasks 1-8 touched that chain; report explicitly either way.
