# Phase 1 Closeout — 5 Targeted Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close out Phase 1 with five targeted fixes: hide the public login entry points, hide the `/browse` sidebar shortcut for staff, widen internal + public search across many fields, rename `users.registration_number` → `users.employee_number`, and add a signed-email invite flow for staff account creation.

**Architecture:** Fixes 1–2 are Vue-only edits. Fix 3 adds a `SearchService::searchInternal()` that unions a pending-proposals query with an all-projects query into one manually-built paginator, plus shared text-match helpers reused by `PublicController::browse()`. Fix 4 is a standalone reversible `renameColumn` migration plus a codebase-wide identifier sweep (users-field references only). Fix 5 adds a `staff_invitations` table (hashed token), a `StaffInvitationMail` Mailable with an Arabic RTL Blade template, a guest-only `SetupPasswordController` that validates a `URL::temporarySignedRoute` link + hashed token + expiry + single-use, and rewires `Admin\UserController::store()` to create a locked (`password` NULL, `is_active` false) user and email the invite instead of setting a password.

**Tech Stack:** Laravel 12.62 (PHP 8.2), Vue 3 + Inertia 2 + TypeScript, Tailwind 3, Spatie laravel-permission 6, Pest 3, Vite. Dev mail: Mailpit on `127.0.0.1:1025`. Tests: SQLite `:memory:`, `MAIL_MAILER=array`.

**Spec:** The user's 5-fix brief in the session prompt (2026-08-29). This plan argues from that brief; executors read both.

## Global Constraints

- **Do NOT merge to main.** Push the branch and open a PR against `main`; report the PR URL.
- **Branch:** `worktree-fix+phase1-closeout` (already created off `origin/main` in worktree `.claude/worktrees/fix+phase1-closeout`). `vendor/` is a real copy (not a junction — a junctioned `vendor` breaks `Application::inferBasePath()` in tests); `node_modules/` is a junction; `.env` is copied from the main checkout.
- **Baseline: `npm run build` has been run and `php artisan test` is 261 passed / 0 failed** — the documented baseline. (Before the build, ~70 tests that render an Inertia page through the full HTTP stack fail with "Vite manifest not found"; always keep `public/build/manifest.json` fresh — run `npm run build` after every batch of Vue changes and before any full `php artisan test`.)
- **Arabic RTL UI.** English only in code; Arabic only in user-facing strings. Match existing Tailwind brand tokens (`primary` `#103A52`, `primary-dark`, `primary-light` `#4FA8C9`, `surface`, `background`, `border`, `text-dark`, `text-muted`; fonts `font-display` Tajawal / `font-body` IBM Plex Sans Arabic).
- **Soft delete is `is_deleted` boolean**, never `deleted_at`.
- **No REST API** — all responses via `Inertia::render()`.
- **Form validation via Laravel Form Requests.**
- **"في انتظار الموافقة" is not a valid system state** — must never appear in any UI.
- **`proposal_students.registration_number` is a genuine student registration number — NEVER rename it.** Only `users.registration_number` is renamed (Fix 4).
- **TDD is mandatory for Fix 4 and Fix 5** (both security-adjacent): write the failing test, run it red, implement minimally, run it green, commit.
- **Verification bar at the end:** full `php artisan test` green (≥ recorded baseline + new tests), `npm run build` clean, real Playwright walkthrough on a port other than 8000 with a real Mailpit inbox screenshot, `/code-review` on the full branch diff.
- **Update `CLAUDE.md` "Current Status" and `PROGRESS.md`** after the work is complete (per project rule in memory).
- Password rules for Fix 5 setup form: `Illuminate\Validation\Rules\Password::defaults()` + `confirmed` (the existing `/login` form only requires `required|string`, so `Password::defaults()` is stricter and is what `NewPasswordController` already uses).
- Signed URLs: `URL::temporarySignedRoute()` with a 24h expiry; the setup controller **also** re-checks `hasValidSignature()` manually (to render a friendly page instead of a bare 403) plus the hashed DB token, expiry, single-use, email-match, and not-already-activated.
- Rate limit: `throttle:setup-password` = 5/hour per IP on the setup **POST**.
- **Never log the raw token.** Log every setup attempt (success + failure) with `user_id` (if resolvable), IP, and outcome via `Log::info`/`Log::warning`.

---

## File Structure

**Fix 1 (login CTAs):**
- Modify: `resources/js/pages/Welcome.vue` — remove header login button, hero secondary login button, and rework the bottom CTA band to point at `/browse`.

**Fix 2 (sidebar entry):**
- Modify: `resources/js/components/AppSidebar.vue` — drop the `تصفح المشاريع` nav item from all 5 role menus; drop the now-unused `BookOpen` import.

**Fix 3 (expanded search):**
- Modify: `app/Services/SearchService.php` — add `searchInternal(array): LengthAwarePaginator`, `proposalTextMatch(Builder, string): Builder`, `projectTextMatch(Builder, string): void`.
- Modify: `app/Http/Controllers/SearchController.php` — `index()` calls `searchInternal()`.
- Modify: `app/Http/Controllers/PublicController.php` — `browse()` search closure uses `SearchService::projectTextMatch()` (inject the service).
- Modify: `resources/js/pages/Search/Index.vue` — render normalized rows with entity badges + correct per-row route.
- Test: `tests/Feature/Search/ExpandedSearchTest.php` (new).

**Fix 4 (column rename):**
- Create: `database/migrations/2026_08_29_000100_rename_registration_number_to_employee_number_on_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/UpdateUserRequest.php`, `app/Http/Controllers/Admin/UserController.php`, `resources/js/pages/Admin/Users/Index.vue`, `CLAUDE.md`, `PROGRESS.md`.
- Test: `tests/Feature/Admin/UserManagementTest.php` (adjust), `tests/Feature/Admin/EmployeeNumberRenameTest.php` (new).

**Fix 5 (email-link account creation):**
- Create: `database/migrations/2026_08_29_000200_create_staff_invitations_table.php`
- Create: `database/migrations/2026_08_29_000300_make_users_password_nullable.php`
- Create: `app/Models/StaffInvitation.php`
- Create: `app/Mail/StaffInvitationMail.php`
- Create: `resources/views/emails/staff-invitation.blade.php`
- Create: `app/Http/Controllers/Auth/SetupPasswordController.php`
- Create: `app/Http/Requests/SetupPasswordRequest.php`
- Create: `resources/js/pages/auth/SetupPassword.vue`
- Create: `resources/js/pages/auth/InvitationInvalid.vue`
- Modify: `routes/auth.php`, `routes/web.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Controllers/Admin/UserController.php`, `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/Auth/LoginRequest.php`, `resources/js/pages/Admin/Users/Index.vue`, `CLAUDE.md`, `PROGRESS.md`.
- Test: `tests/Feature/Auth/StaffInvitationTest.php` (new), `tests/Feature/Admin/UserManagementTest.php` (adjust).

---

## Interfaces (cross-task contract)

- `SearchService::searchInternal(array $filters): \Illuminate\Pagination\LengthAwarePaginator` — items are **arrays** shaped:
  ```
  [
    'type' => 'proposal'|'project',
    'entity_label' => 'مقترح'|'مشروع قيد التنفيذ'|'مشروع مؤرشف',
    'route' => 'proposals.show'|'projects.show',
    'route_id' => int,
    'title' => string,
    'description' => ?string,
    'academic_year' => string,
    'department' => ?string,
    'specialization' => ?string,
    'supervisor' => ?string,
    'students' => string[],   // full names
    'created_at' => string,   // ISO
  ]
  ```
- `SearchService::proposalTextMatch(\Illuminate\Contracts\Database\Eloquent\Builder $q, string $term): \Illuminate\Contracts\Database\Eloquent\Builder` — `$term` is already `mb_strtolower(trim(...))`. Adds one grouped `where(fn)` OR-ing title/description/academic_year/students.full_name/supervisor.name/department.name/specialization.name LIKE `%term%`.
- `SearchService::projectTextMatch(\Illuminate\Contracts\Database\Eloquent\Builder $projectQuery, string $term): void` — adds a grouped `where(fn)` OR-ing `whereHas('proposal', proposalTextMatch)` and `whereHas('examiners', examiners.full_name LIKE)`.
- `StaffInvitation::issueFor(\App\Models\User $user): string` — deletes any prior rows for `$user`, inserts a fresh row (`token_hash` = `hash('sha256', $plain)`, `expires_at` = `now()->addHours(24)`, `used_at` = null, `created_at` = now()), returns the **plain** token.
- `StaffInvitation::signedUrlFor(\App\Models\User $user, string $plainToken): string` — `URL::temporarySignedRoute('staff.setup-password', <that user's invitation expires_at>, ['token' => $plainToken, 'email' => $user->email])`.
- `StaffInvitationMail(\App\Models\User $invitee, \App\Models\User $inviter, string $setupUrl)` — subject `دعوة لإنشاء حسابك في منظومة أرشفة مشاريع التخرج`, view `emails.staff-invitation`; the constructor args are public props (`$invitee`, `$inviter`, `$setupUrl`).
- Route names: `staff.setup-password` (GET `/setup-password/{token}`), `staff.setup-password.store` (POST same URI), `admin.users.resend-invitation` (POST `/admin/users/{user}/resend-invitation`).

---

## PART A — Fix 1: Remove login entry points from the public landing page

Decision (confirmed with user): remove **all three** login CTAs from `Welcome.vue` (header button, hero secondary button, bottom CTA band). Keep the authed-only `لوحة التحكم` header link for logged-in visitors. Keep the `تصفح المشاريع` browse CTA.

### Task A1: Strip login CTAs from Welcome.vue

**Files:**
- Modify: `resources/js/pages/Welcome.vue`

- [ ] **Step 1: Edit the header block.** Replace the `<div class="flex items-center gap-3">…</div>` inside `<header>` (currently lines ~50–66) with a version that keeps only the authed dashboard link and drops the `<template v-else>` login button entirely:

```vue
                <div class="flex items-center gap-3">
                    <Link
                        v-if="auth?.user"
                        :href="route('dashboard')"
                        class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-primary text-white font-body text-sm font-medium hover:bg-primary-dark transition-colors"
                    >
                        لوحة التحكم
                    </Link>
                </div>
```

- [ ] **Step 2: Edit the hero CTA row.** In the `<!-- CTA buttons -->` block (lines ~97–111), remove the second `<Link :href="route('login')">تسجيل الدخول</Link>`. Keep only the `تصفح المشاريع` link. The wrapping `<div class="flex items-center justify-center flex-wrap gap-4">` stays.

- [ ] **Step 3: Rework the bottom CTA band.** In `<!-- CTA BAND -->` (lines ~159–173), replace the heading/description/link so it drives browsing, not login:

```vue
        <!-- CTA BAND -->
        <section class="bg-primary py-14">
            <div class="max-w-xl mx-auto px-6 text-center">
                <h2 class="font-display font-bold text-2xl text-white mb-3">تصفّح أرشيف المشاريع</h2>
                <p class="font-body text-white/80 mb-8 leading-relaxed">
                    استعرض مشاريع التخرج المؤرشفة وابحث فيها بحسب القسم أو التخصص أو العام الدراسي.
                </p>
                <Link
                    :href="route('public.browse')"
                    class="inline-flex items-center gap-2 px-8 py-3 rounded-lg bg-white text-primary font-body font-semibold text-base hover:bg-background transition-colors shadow-md"
                >
                    تصفح المشاريع
                </Link>
            </div>
        </section>
```

- [ ] **Step 4: Verify no dead references.** `route('login')` must no longer appear in `Welcome.vue`. `grep -n "route('login')" resources/js/pages/Welcome.vue` → no matches. The `Link` import stays (still used).

- [ ] **Step 5: Build check.**

Run: `npm run build`
Expected: completes with no errors.

- [ ] **Step 6: Commit.**

```bash
git add resources/js/pages/Welcome.vue
git commit -m "fix: remove login entry points from the public landing page"
```

---

## PART B — Fix 2: Remove "تصفح المشاريع" from every sidebar menu

### Task B1: Drop the browse nav item from AppSidebar.vue

**Files:**
- Modify: `resources/js/components/AppSidebar.vue`

- [ ] **Step 1: Remove the nav item from all 5 menus.** In `navByRole` (lines ~14–62), delete the line `{ title: 'تصفح المشاريع', href: '/browse', icon: BookOpen },` from each of: `super_admin` (line ~27), `dept_manager` (line ~38), `dept_staff` (line ~46), `supervisor` (line ~53), `default` (line ~59). Nothing else in those arrays changes.

- [ ] **Step 2: Drop the unused import.** In the `lucide-vue-next` import (line 7), remove `BookOpen` (it has no other use in the file). New line:

```ts
import { BarChart2, Building2, FileText, FolderOpen, LayoutGrid, Search, Upload, UserCheck, Users } from 'lucide-vue-next';
```

- [ ] **Step 3: Verify.** `grep -n "browse\|BookOpen" resources/js/components/AppSidebar.vue` → no matches.

- [ ] **Step 4: Build check.**

Run: `npm run build`
Expected: completes with no errors.

- [ ] **Step 5: Commit.**

```bash
git add resources/js/components/AppSidebar.vue
git commit -m "fix: remove /browse shortcut from authenticated sidebar menus"
```

---

## PART C — Fix 3: Expanded search across many fields

**Context the executor needs:**
- `SearchService::searchProposals()` currently powers BOTH `/proposals` (`ProposalController::index`) and `/search` (`SearchController::index`). **Do not change `searchProposals()`** — `/proposals` stays as-is and the existing 15 `SearchTest.php` tests (all target `proposals.index` / `search.suggestions`, none target `search.index` data) stay green. Add a NEW `searchInternal()` used only by `SearchController::index()`.
- `Proposal::STATUS_PENDING = 2` (مقترح, not instantiated), `STATUS_ARCHIVED = 1` (instantiated). `Project::STATUS_IN_PROGRESS = 1` (قيد التنفيذ), `STATUS_ARCHIVED = 2` (مؤرشف).
- Every instantiated (`STATUS_ARCHIVED`) proposal has exactly one linked `Project`. To avoid showing the same work twice, the internal search's **proposal query is restricted to `status_id = STATUS_PENDING`**, and the **project query covers all non-deleted projects** (in-progress + archived). Together they cover every reachable item once.
- Examiners attach only to `projects` (via `project_examiners` pivot, `examiners.full_name`). Proposals have no examiners — examiner matching applies to the project query only.
- Public `/browse` scope is unchanged: archived projects only. Fix 3 only widens which fields its `search` param matches.
- `detectSimilarity()` stays title-only (spec: semantically correct). `SearchController::suggestions()` stays title-only on proposals (out of scope, minimise risk).

### Task C1: Add `proposalTextMatch` / `projectTextMatch` helpers + failing test for public browse widening

**Files:**
- Modify: `app/Services/SearchService.php`
- Modify: `app/Http/Controllers/PublicController.php`
- Test: `tests/Feature/Search/ExpandedSearchTest.php`

- [ ] **Step 1: Write the failing test** for `/browse` matching a supervisor name and an examiner name.

```php
<?php

use App\Models\Department;
use App\Models\Examiner;
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

/** Build an archived Project whose proposal carries the given attributes. */
function makeArchivedProject(array $proposalOverrides = [], ?User $supervisor = null): Project {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor ??= userWithRole('supervisor');

    $proposal = Proposal::factory()->create(array_merge([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
        'is_deleted'        => false,
    ], $proposalOverrides));

    $project = $proposal->instantiateProject($supervisor);
    $project->update(['status_id' => Project::STATUS_ARCHIVED]);

    return $project->fresh();
}

test('public browse matches a supervisor name', function () {
    $sup = userWithRole('supervisor', ['name' => 'Dr Farouk Almansour']);
    makeArchivedProject(['title' => 'Unrelated Title A'], $sup);
    makeArchivedProject(['title' => 'Unrelated Title B']);

    $this->get(route('public.browse', ['search' => 'Farouk']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});

test('public browse matches an examiner name', function () {
    $project = makeArchivedProject(['title' => 'Signal Processing Rig']);
    $examiner = Examiner::factory()->create(['full_name' => 'Prof Widad Alkhattabi']);
    $project->examiners()->attach($examiner->id);
    makeArchivedProject(['title' => 'Other Project']);

    $this->get(route('public.browse', ['search' => 'Widad']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});
```

- [ ] **Step 2: Run it red.**

Run: `php artisan test tests/Feature/Search/ExpandedSearchTest.php`
Expected: both FAIL (`projects.data` has 0, current browse only matches title/description).

- [ ] **Step 3: Add the helpers to `SearchService`.** Add `use Illuminate\Contracts\Database\Eloquent\Builder;` at the top. Add these methods:

```php
    /**
     * OR-match a Proposal query across title, description, academic_year,
     * student names, supervisor name, department name, specialization name.
     * $term must already be trimmed + mb_strtolower'd.
     */
    public function proposalTextMatch(Builder $query, string $term): Builder
    {
        $like = '%' . $term . '%';

        return $query->where(function (Builder $q) use ($like) {
            $q->whereRaw('LOWER(proposals.title) LIKE ?', [$like])
              ->orWhereRaw('LOWER(proposals.description) LIKE ?', [$like])
              ->orWhereRaw('LOWER(proposals.academic_year) LIKE ?', [$like])
              ->orWhereHas('students', fn (Builder $s) => $s->whereRaw('LOWER(proposal_students.full_name) LIKE ?', [$like]))
              ->orWhereHas('supervisor', fn (Builder $u) => $u->whereRaw('LOWER(users.name) LIKE ?', [$like]))
              ->orWhereHas('department', fn (Builder $d) => $d->whereRaw('LOWER(departments.name) LIKE ?', [$like]))
              ->orWhereHas('specialization', fn (Builder $sp) => $sp->whereRaw('LOWER(specializations.name) LIKE ?', [$like]));
        });
    }

    /**
     * OR-match a Project query across everything proposalTextMatch covers
     * (via the linked proposal) plus assigned examiner names.
     * $term must already be trimmed + mb_strtolower'd.
     */
    public function projectTextMatch(Builder $projectQuery, string $term): void
    {
        $like = '%' . $term . '%';

        $projectQuery->where(function (Builder $q) use ($term, $like) {
            $q->whereHas('proposal', fn (Builder $p) => $this->proposalTextMatch($p, $term))
              ->orWhereHas('examiners', fn (Builder $e) => $e->whereRaw('LOWER(examiners.full_name) LIKE ?', [$like]));
        });
    }
```

- [ ] **Step 4: Wire `PublicController::browse()` to use it.** Inject the service and replace the search closure.

```php
    public function __construct(private readonly \App\Services\SearchService $search) {}
```

Replace the `if ($search = $request->input('search')) { … }` block with:

```php
        if ($search = trim((string) $request->input('search'))) {
            $this->search->projectTextMatch($query, mb_strtolower($search));
        }
```

- [ ] **Step 5: Run it green.**

Run: `php artisan test tests/Feature/Search/ExpandedSearchTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Regression check the public browse suite.**

Run: `php artisan test tests/Feature/Public/PublicBrowseTest.php`
Expected: PASS (14 tests) — title/department/year filters still work.

- [ ] **Step 7: Commit.**

```bash
git add app/Services/SearchService.php app/Http/Controllers/PublicController.php tests/Feature/Search/ExpandedSearchTest.php
git commit -m "feat: widen public browse search across students, supervisor, examiners, dept, specialization"
```

### Task C2: `SearchService::searchInternal()` unifying proposals + projects

**Files:**
- Modify: `app/Services/SearchService.php`
- Modify: `app/Http/Controllers/SearchController.php`
- Test: `tests/Feature/Search/ExpandedSearchTest.php`

- [ ] **Step 1: Add failing tests** to `ExpandedSearchTest.php`:

```php
test('internal search finds a project by a student name only', function () {
    $project = makeArchivedProject(['title' => 'Autonomous Rover']);
    $project->proposal->students()->create([
        'full_name' => 'Khaled Bouzid', 'registration_number' => 'S-9001', 'status' => 'active',
    ]);
    makeArchivedProject(['title' => 'Decoy Project']);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index', ['search' => 'Bouzid']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Search/Index')
            ->has('results.data', 1)
            ->where('results.data.0.title', 'Autonomous Rover')
            ->where('results.data.0.type', 'project')
        );
});

test('internal search finds a project by an examiner name only', function () {
    $project = makeArchivedProject(['title' => 'Thermal Camera']);
    $examiner = \App\Models\Examiner::factory()->create(['full_name' => 'Dr Munir Alraqi']);
    $project->examiners()->attach($examiner->id);
    makeArchivedProject(['title' => 'Decoy']);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index', ['search' => 'Munir']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results.data', 1)
            ->where('results.data.0.type', 'project'));
});

test('internal search shows a pending proposal that public browse hides', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    Proposal::factory()->create([
        'title' => 'Pending Idea About Drones',
        'department_id' => $dept->id, 'specialization_id' => $spec->id,
        'supervisor_id' => userWithRole('supervisor')->id,
        'status_id' => Proposal::STATUS_PENDING, 'is_deleted' => false,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index', ['search' => 'Drones']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results.data', 1)
            ->where('results.data.0.type', 'proposal')
            ->where('results.data.0.entity_label', 'مقترح'));

    $this->get(route('public.browse', ['search' => 'Drones']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 0));
});

test('an archived project appears in both internal search and public browse', function () {
    makeArchivedProject(['title' => 'Shared Visible Project']);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index', ['search' => 'Shared Visible']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results.data', 1)
            ->where('results.data.0.entity_label', 'مشروع مؤرشف'));

    $this->get(route('public.browse', ['search' => 'Shared Visible']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});

test('internal search is case-insensitive on Arabic input', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    Proposal::factory()->create([
        'title' => 'نظام إدارة المكتبة الذكية',
        'department_id' => $dept->id, 'specialization_id' => $spec->id,
        'supervisor_id' => userWithRole('supervisor')->id,
        'status_id' => Proposal::STATUS_PENDING, 'is_deleted' => false,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index', ['search' => '  المكتبة  ']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results.data', 1));
});

test('empty internal search returns all reachable proposals and projects', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    Proposal::factory()->count(2)->create([
        'department_id' => $dept->id, 'specialization_id' => $spec->id,
        'supervisor_id' => userWithRole('supervisor')->id,
        'status_id' => Proposal::STATUS_PENDING, 'is_deleted' => false,
    ]);
    makeArchivedProject(['title' => 'Archived One']);

    $this->actingAs(userWithRole('super_admin'))
        ->get(route('search.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('results.data', 3));
});
```

- [ ] **Step 2: Run red.**

Run: `php artisan test tests/Feature/Search/ExpandedSearchTest.php`
Expected: the 6 new tests FAIL (`results` prop does not exist; `search.index` still returns `projects`).

- [ ] **Step 3: Implement `searchInternal()`.** Add imports to `SearchService`: `use App\Models\Project;`, `use Illuminate\Pagination\LengthAwarePaginator;` (already present). Add:

```php
    public function searchInternal(array $filters): LengthAwarePaginator
    {
        $term = isset($filters['search']) ? mb_strtolower(trim((string) $filters['search'])) : '';

        $proposalRows = Proposal::query()
            ->where('is_deleted', false)
            ->where('status_id', Proposal::STATUS_PENDING)
            ->with(['department:id,name', 'specialization:id,name', 'supervisor:id,name', 'students:id,proposal_id,full_name'])
            ->when($term !== '', fn ($q) => $this->proposalTextMatch($q, $term))
            ->get()
            ->map(fn (Proposal $p) => [
                'type'          => 'proposal',
                'entity_label'  => 'مقترح',
                'route'         => 'proposals.show',
                'route_id'      => $p->id,
                'title'         => $p->title,
                'description'   => $p->description,
                'academic_year' => $p->academic_year,
                'department'    => $p->department?->name,
                'specialization'=> $p->specialization?->name,
                'supervisor'    => $p->supervisor?->name,
                'students'      => $p->students->pluck('full_name')->all(),
                'created_at'    => $p->created_at?->toIso8601String(),
            ]);

        $projectQuery = Project::query()
            ->where('is_deleted', false)
            ->with([
                'proposal.department:id,name', 'proposal.specialization:id,name',
                'proposal.supervisor:id,name', 'proposal.students:id,proposal_id,full_name',
                'status:id,status_name',
            ]);

        if ($term !== '') {
            $this->projectTextMatch($projectQuery, $term);
        }

        $projectRows = $projectQuery->get()->map(fn (Project $project) => [
            'type'           => 'project',
            'entity_label'   => $project->status_id === Project::STATUS_ARCHIVED ? 'مشروع مؤرشف' : 'مشروع قيد التنفيذ',
            'route'          => 'projects.show',
            'route_id'       => $project->id,
            'title'          => $project->proposal->title,
            'description'    => $project->proposal->description,
            'academic_year'  => $project->proposal->academic_year,
            'department'     => $project->proposal->department?->name,
            'specialization' => $project->proposal->specialization?->name,
            'supervisor'     => $project->proposal->supervisor?->name,
            'students'       => $project->proposal->students->pluck('full_name')->all(),
            'created_at'     => $project->created_at?->toIso8601String(),
        ]);

        $merged = $proposalRows->concat($projectRows)
            ->sortByDesc('created_at')
            ->values();

        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $merged->forPage($page, $perPage)->values(),
            $merged->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $filters]
        );
    }
```

- [ ] **Step 4: Wire `SearchController::index()`.**

```php
    public function index(Request $request): Response
    {
        $filters = $request->only(['search']);

        return Inertia::render('Search/Index', [
            'results' => $this->search->searchInternal($filters),
            'filters' => $filters,
        ]);
    }
```

(Drop `filterOptions` from this action — the redesigned page uses a single search box only. Leave `suggestions()` untouched.)

- [ ] **Step 5: Run green.**

Run: `php artisan test tests/Feature/Search/ExpandedSearchTest.php`
Expected: all 8 tests PASS.

- [ ] **Step 6: Full search + proposals regression.**

Run: `php artisan test tests/Feature/Search/SearchTest.php tests/Feature/Proposal tests/Feature/Public`
Expected: PASS (no change to `searchProposals()` or `/proposals`).

- [ ] **Step 7: Commit.**

```bash
git add app/Services/SearchService.php app/Http/Controllers/SearchController.php tests/Feature/Search/ExpandedSearchTest.php
git commit -m "feat: unified internal search across proposals and projects with entity badges"
```

### Task C3: Rewrite Search/Index.vue for the unified result shape

**Files:**
- Modify: `resources/js/pages/Search/Index.vue`

- [ ] **Step 1: Replace the `<script setup>` block** to consume `results` + normalized rows. Keep `SearchBar` + live suggestions (endpoint unchanged), keep debounced `router.get`. New types:

```ts
interface ResultRow {
    type: 'proposal' | 'project';
    entity_label: string;
    route: string;
    route_id: number;
    title: string;
    description: string | null;
    academic_year: string;
    department: string | null;
    specialization: string | null;
    supervisor: string | null;
    students: string[];
}
interface PaginationLink { url: string | null; label: string; active: boolean }
interface PaginatedResults {
    data: ResultRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}
const props = defineProps<{
    results: PaginatedResults;
    filters: { search?: string };
}>();
```

Badge class map:

```ts
const badgeClass: Record<string, string> = {
    'مقترح': 'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    'مشروع قيد التنفيذ': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
    'مشروع مؤرشف': 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
};
```

Keep `escapeHtml` + `highlight` helpers. `doSearch(q)` → `router.get(route('search.index'), q ? { search: q } : {}, { preserveState: true, replace: true })`.

- [ ] **Step 2: Replace the results template.** Drop the "grouped by department" sections; render a flat list. Each row:

```vue
<a
    v-for="row in results.data"
    :key="row.type + '-' + row.route_id"
    :href="route(row.route, [row.route_id])"
    class="block rounded-lg border border-gray-200 bg-white p-4 transition hover:border-blue-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:hover:border-blue-700"
>
    <div class="flex items-center gap-2">
        <span :class="badgeClass[row.entity_label] ?? 'bg-gray-100 text-gray-700'" class="rounded-full px-2.5 py-0.5 text-xs font-medium">
            {{ row.entity_label }}
        </span>
        <p class="text-sm font-semibold text-blue-600 dark:text-blue-400" v-html="highlight(row.title)" />
    </div>
    <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
        <span v-if="row.department">🏛️ {{ row.department }}</span>
        <span v-if="row.specialization">📚 {{ row.specialization }}</span>
        <span v-if="row.academic_year">📅 {{ row.academic_year }}</span>
        <span v-if="row.supervisor">👤 {{ row.supervisor }}</span>
    </div>
    <div v-if="row.students.length" class="mt-1.5 flex flex-wrap gap-1">
        <span v-for="(name, i) in row.students" :key="i" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ name }}</span>
    </div>
    <p v-if="row.description" class="mt-2 line-clamp-2 text-xs text-gray-500 dark:text-gray-400" v-html="highlight(row.description)" />
</a>
```

Results summary line: `تم العثور على {{ results.total }} نتيجة`. Keep the empty-state block. Pagination block: reuse the existing `results.links` loop (rename `projects` → `results`). Heading text: `البحث في المقترحات والمشاريع`. SearchBar placeholder: `ابحث بالعنوان أو الطالب أو المشرف أو الممتحن أو القسم...`.

- [ ] **Step 3: Build check.**

Run: `npm run build`
Expected: no errors.

- [ ] **Step 4: Commit.**

```bash
git add resources/js/pages/Search/Index.vue
git commit -m "feat: render unified search results with entity-type badges and per-row routing"
```

---

## PART D — Fix 4: Rename `users.registration_number` → `users.employee_number` (TDD)

**`registration_number` grep split (verified 2026-08-29 against the branch):**

**(a) `users`-table references → ALL renamed in this Part:**
| File | What |
|---|---|
| `app/Models/User.php:21` | `$fillable` entry |
| `database/factories/UserFactory.php` | (currently ABSENT — add `employee_number`) |
| `app/Http/Requests/StoreUserRequest.php:20` | validation rule + unique constraint |
| `app/Http/Requests/UpdateUserRequest.php:23` | validation rule + `Rule::unique('users','…')->ignore()` |
| `app/Http/Controllers/Admin/UserController.php:24` | `->orWhere('registration_number','like',…)` in `index()` search |
| `app/Http/Controllers/Admin/UserController.php:59` | `store()` create payload |
| `app/Http/Controllers/Admin/UserController.php:77` | `update()` payload |
| `resources/js/pages/Admin/Users/Index.vue:17` | `UserItem` interface field |
| `resources/js/pages/Admin/Users/Index.vue:122,148` | `createForm` / `editForm` field |
| `resources/js/pages/Admin/Users/Index.vue:158` | `openEdit()` assignment |
| `resources/js/pages/Admin/Users/Index.vue:223` | search placeholder text `… أو رقم القيد …` → `… أو الرقم الوظيفي …` |
| `resources/js/pages/Admin/Users/Index.vue:277` | table `<th>رقم القيد</th>` → `الرقم الوظيفي` |
| `resources/js/pages/Admin/Users/Index.vue:300` | table cell `{{ user.registration_number ?? '—' }}` |
| `resources/js/pages/Admin/Users/Index.vue:429,431,434,436` | create-modal label `رقم القيد` + input `v-model` + error bindings |
| `resources/js/pages/Admin/Users/Index.vue:536,538,541,543` | edit-modal label + input + error bindings |
| `CLAUDE.md:38` | `4. users (+ registration_number field)` → employee_number + "staff-only table" note |
| `CLAUDE.md:475` | `✅ User model updated — … registration_number …` |
| `PROGRESS.md:168` | `users` schema table row |
| `PROGRESS.md:418` | `User` model `$fillable` list |

**(b) `proposal_students`-table references → LEFT ALONE (do NOT touch):**
`app/Http/Requests/StoreProposalRequest.php:36`, `app/Http/Requests/UpdateProposalRequest.php:49`, `app/Imports/ProjectsImport.php:111`, `app/Models/ProposalStudent.php:13`, `app/Console/Commands/MigrateProposalProjectData.php:149`, `app/Http/Controllers/ProposalController.php:82,152`, `database/migrations/2026_06_15_100006_create_project_students_table.php:15`, `database/migrations/2026_08_24_160200_create_proposal_students_table.php:15`, `database/seeders/DummyDataSeeder.php:306`, `resources/js/pages/Proposals/Create.vue` (11,33,45,214,217,219,220), `resources/js/pages/Proposals/Edit.vue` (10,24,49,64,237,240,242,243), `resources/js/pages/Proposals/Show.vue` (14,232), `resources/js/pages/Projects/Show.vue` (14,135), `resources/js/pages/Public/Browse.vue:8`, `resources/js/pages/Public/Show.vue` (7,143), `tests/Feature/Models/ProjectModelTest.php:33`, `tests/Feature/Proposal/ProposalTest.php` (26,44,65), `tests/Feature/Roles/RoleVerificationTest.php:99`, `docs/superpowers/plans/2026-08-24-proposal-project-split.md` (historical), `PROGRESS.md:312,539` (`proposal_students` schema + `ProposalStudent` model — already correct).

**Do NOT touch** the original `database/migrations/2026_06_15_100003_add_fields_to_users_table.php` — it legitimately created `registration_number`; the new rename migration runs after it. On `migrate:fresh` the column ends up `employee_number`.

### Task D1: The rename migration

**Files:**
- Create: `database/migrations/2026_08_29_000100_rename_registration_number_to_employee_number_on_users_table.php`
- Test: `tests/Feature/Admin/EmployeeNumberRenameTest.php`

- [ ] **Step 1: Write the failing test.**

```php
<?php

use Illuminate\Support\Facades\Schema;

test('users table has employee_number and not registration_number', function () {
    expect(Schema::hasColumn('users', 'employee_number'))->toBeTrue();
    expect(Schema::hasColumn('users', 'registration_number'))->toBeFalse();
});
```

- [ ] **Step 2: Run red.**

Run: `php artisan test tests/Feature/Admin/EmployeeNumberRenameTest.php`
Expected: FAIL (`employee_number` missing).

- [ ] **Step 3: Write the migration.**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('registration_number', 'employee_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('employee_number', 'registration_number');
        });
    }
};
```

(Laravel 12 renames columns natively on MySQL/MariaDB/SQLite — no `doctrine/dbal` needed. The unique index created by the original migration follows the rename automatically on MariaDB; SQLite rebuilds the table.)

- [ ] **Step 4: Run green.**

Run: `php artisan test tests/Feature/Admin/EmployeeNumberRenameTest.php`
Expected: PASS.

- [ ] **Step 5: Commit.**

```bash
git add database/migrations/2026_08_29_000100_rename_registration_number_to_employee_number_on_users_table.php tests/Feature/Admin/EmployeeNumberRenameTest.php
git commit -m "feat: rename users.registration_number to employee_number (migration)"
```

### Task D2: Model, factory, form requests, controller

**Files:**
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/UpdateUserRequest.php`, `app/Http/Controllers/Admin/UserController.php`
- Test: `tests/Feature/Admin/EmployeeNumberRenameTest.php`

- [ ] **Step 1: Add failing tests** to `EmployeeNumberRenameTest.php`:

```php
use App\Models\User;

test('UserFactory produces employee_number and not registration_number', function () {
    $user = User::factory()->create();
    expect($user->employee_number)->not->toBeNull();
    expect($user->getAttributes())->not->toHaveKey('registration_number');
});
```

- [ ] **Step 2: Run red.**

Run: `php artisan test tests/Feature/Admin/EmployeeNumberRenameTest.php`
Expected: the new test FAILs.

- [ ] **Step 3: `app/Models/User.php`** — change `$fillable` entry `'registration_number'` → `'employee_number'`. (No casts/accessors/scopes reference it.)

- [ ] **Step 4: `database/factories/UserFactory.php`** — add to `definition()` return array:

```php
            'employee_number'  => 'EMP-' . fake()->unique()->numerify('######'),
```

- [ ] **Step 5: `app/Http/Requests/StoreUserRequest.php`** — rename the rule key + column:

```php
            'employee_number' => ['nullable', 'string', 'unique:users,employee_number'],
```

- [ ] **Step 6: `app/Http/Requests/UpdateUserRequest.php`**:

```php
            'employee_number' => ['nullable', 'string', Rule::unique('users', 'employee_number')->ignore($user)],
```

- [ ] **Step 7: `app/Http/Controllers/Admin/UserController.php`** — three edits:
  - `index()` search: `->orWhere('employee_number', 'like', "%{$search}%");`
  - `store()` payload: `'employee_number' => $validated['employee_number'] ?? null,`
  - `update()` payload: `'employee_number' => $validated['employee_number'] ?? null,`

- [ ] **Step 8: Run green.**

Run: `php artisan test tests/Feature/Admin/EmployeeNumberRenameTest.php tests/Feature/Admin/UserManagementTest.php`
Expected: PASS.

- [ ] **Step 9: Commit.**

```bash
git add app/Models/User.php database/factories/UserFactory.php app/Http/Requests/StoreUserRequest.php app/Http/Requests/UpdateUserRequest.php app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/EmployeeNumberRenameTest.php
git commit -m "feat: rename users.registration_number to employee_number (model, factory, requests, controller)"
```

### Task D3: Frontend + docs + existing-test realignment

**Files:**
- Modify: `resources/js/pages/Admin/Users/Index.vue`, `tests/Feature/Admin/UserManagementTest.php`, `CLAUDE.md`, `PROGRESS.md`

- [ ] **Step 1: `resources/js/pages/Admin/Users/Index.vue`** — mechanical rename `registration_number` → `employee_number` at every listed line (interface field, `createForm`, `editForm`, `openEdit`, table cell, both modal inputs + `v-model` + `:class` error bindings + `errors.*` refs). Arabic label/placeholder `رقم القيد` → `الرقم الوظيفي` at: search input `placeholder` (~line 223), table `<th>` (~277), create-modal `<label>` (~429), edit-modal `<label>` (~536).

- [ ] **Step 2: `tests/Feature/Admin/UserManagementTest.php`** — no `registration_number` literal exists in this file today, so nothing to rename. Run it to confirm the rename didn't break it:

Run: `php artisan test tests/Feature/Admin/UserManagementTest.php`
Expected: PASS (9 tests) — unless Part E already landed (then its store changes are covered by Part E's own test adjustments).

- [ ] **Step 3: `CLAUDE.md`** — line ~38: `4. users (+ employee_number field) — STAFF-ONLY table (students never log in; they live in proposal_students.registration_number, a genuine student number)`. Line ~475: `registration_number` → `employee_number`. Add a one-liner clarifying `users` is staff-only and its number is an employee/staff number, distinct from the student `proposal_students.registration_number`.

- [ ] **Step 4: `PROGRESS.md`** — Section 2 `users` table: rename the `registration_number` row to `employee_number`, description "Staff/employee number (college staff only)". Section 3 `User` model `$fillable`: `registration_number` → `employee_number`.

- [ ] **Step 5: Build + full-suite check.**

Run: `npm run build && php artisan test`
Expected: build clean; suite green vs the recorded baseline + new Fix 3/4 tests. Grep the `tests/` tree once more for `users.registration_number` — expected: none outside the `proposal_students` set.

- [ ] **Step 6: Commit.**

```bash
git add resources/js/pages/Admin/Users/Index.vue tests/Feature/Admin/UserManagementTest.php CLAUDE.md PROGRESS.md
git commit -m "feat: rename registration_number to employee_number across admin UI and docs"
```

---

## PART E — Fix 5: Secure account creation via signed email link (TDD)

**Executor context:**
- Full Breeze auth exists, including `/forgot-password` + `/reset-password` (guest group in `routes/auth.php`). **Do not touch the password-reset flow.**
- `users.password` is currently `NOT NULL`; a new migration in this Part makes it nullable.
- `is_active` is NOT currently gated at login (the toggle has no enforcement). This Part adds an `is_active` gate to `LoginRequest` (confirmed with user).
- New invited users get `email_verified_at` set to `now()` at setup completion (clicking the emailed link IS verification) so the post-login redirect to `/dashboard` (which has `verified` middleware) doesn't bounce to `verification.notice`.
- Admin user management is entirely modals in `Admin/Users/Index.vue` — there is no `/admin/users/{id}` page. The "resend invitation" control is a **row action** in that table (confirmed with user), shown only when `has_password` is false.
- Dev mail = Mailpit `127.0.0.1:1025` (already in `.env`). **Mailpit is NOT currently running** — it is only needed for the Playwright walkthrough, not for tests (`MAIL_MAILER=array` in `phpunit.xml`). Flag in the final report: the walkthrough needs `mailpit` started (`mailpit` binary, or `docker run -p 1025:1025 -p 8025:8025 axllent/mailpit`).
- The email logo: `public/images/logo.png` exists; embed it in the Blade via `$message->embed(public_path('images/logo.png'))`.

### Task E1: `staff_invitations` table + nullable password migration + `StaffInvitation` model

**Files:**
- Create: `database/migrations/2026_08_29_000200_create_staff_invitations_table.php`
- Create: `database/migrations/2026_08_29_000300_make_users_password_nullable.php`
- Create: `app/Models/StaffInvitation.php`
- Test: `tests/Feature/Auth/StaffInvitationTest.php`

- [ ] **Step 1: Write the failing test.**

```php
<?php

use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('staff_invitations schema exists and users.password is nullable', function () {
    expect(Schema::hasTable('staff_invitations'))->toBeTrue();
    expect(Schema::hasColumns('staff_invitations', ['user_id', 'token_hash', 'expires_at', 'used_at', 'created_at']))->toBeTrue();

    $user = User::factory()->create(['password' => null]);
    expect($user->fresh()->password)->toBeNull();
});

test('issueFor stores a hashed token, 24h expiry, and returns the plain token', function () {
    $user = User::factory()->create();
    $plain = StaffInvitation::issueFor($user);

    expect($plain)->toBeString()->and(mb_strlen($plain))->toBeGreaterThanOrEqual(40);
    $row = StaffInvitation::where('user_id', $user->id)->firstOrFail();
    expect($row->token_hash)->toBe(hash('sha256', $plain));
    expect($row->used_at)->toBeNull();
    expect($row->expires_at->diffInHours(now()))->toBeGreaterThanOrEqual(23);
});

test('issueFor replaces any prior invitation for the same user', function () {
    $user = User::factory()->create();
    $first = StaffInvitation::issueFor($user);
    $second = StaffInvitation::issueFor($user);

    expect(StaffInvitation::where('user_id', $user->id)->count())->toBe(1);
    expect(StaffInvitation::where('token_hash', hash('sha256', $first))->exists())->toBeFalse();
    expect(StaffInvitation::where('token_hash', hash('sha256', $second))->exists())->toBeTrue();
});
```

- [ ] **Step 2: Run red.**

Run: `php artisan test tests/Feature/Auth/StaffInvitationTest.php`
Expected: FAIL (no table, no model).

- [ ] **Step 3: `create_staff_invitations_table` migration.**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->index();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitations');
    }
};
```

- [ ] **Step 4: `make_users_password_nullable` migration.**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 5: `app/Models/StaffInvitation.php`.**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class StaffInvitation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'used_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Issue a fresh single-use token for $user, replacing any prior one. Returns the PLAIN token. */
    public static function issueFor(User $user): string
    {
        static::where('user_id', $user->id)->delete();

        $plain = Str::random(64);

        static::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHours(24),
            'used_at'    => null,
        ]);

        return $plain;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public static function signedUrlFor(User $user, string $plainToken): string
    {
        $expiresAt = static::where('user_id', $user->id)->value('expires_at') ?? now()->addHours(24);

        return URL::temporarySignedRoute('staff.setup-password', $expiresAt, [
            'token' => $plainToken,
            'email' => $user->email,
        ]);
    }
}
```

- [ ] **Step 6: Run green.**

Run: `php artisan test tests/Feature/Auth/StaffInvitationTest.php`
Expected: PASS (3 tests).

- [ ] **Step 7: Commit.**

```bash
git add database/migrations/2026_08_29_000200_create_staff_invitations_table.php database/migrations/2026_08_29_000300_make_users_password_nullable.php app/Models/StaffInvitation.php tests/Feature/Auth/StaffInvitationTest.php
git commit -m "feat: staff_invitations table, nullable users.password, StaffInvitation model"
```

### Task E2: `StaffInvitationMail` + Arabic RTL Blade template

**Files:**
- Create: `app/Mail/StaffInvitationMail.php`
- Create: `resources/views/emails/staff-invitation.blade.php`
- Test: `tests/Feature/Auth/StaffInvitationTest.php`

- [ ] **Step 1: Write the failing test.**

```php
use App\Mail\StaffInvitationMail;

test('the invitation mailable has the Arabic subject and renders the setup URL and inviter name', function () {
    $invitee = User::factory()->create(['name' => 'Rania Saleh']);
    $inviter = User::factory()->create(['name' => 'Admin Boss']);
    $url = 'https://example.test/setup-password/abc123?email=rania%40x.test&expires=1&signature=x';

    $mailable = new StaffInvitationMail($invitee, $inviter, $url);

    $mailable->assertHasSubject('دعوة لإنشاء حسابك في منظومة أرشفة مشاريع التخرج');
    $mailable->assertSeeInHtml('Admin Boss');
    $mailable->assertSeeInHtml('إنشاء كلمة المرور');
    $mailable->assertSeeInHtml($url);
});
```

- [ ] **Step 2: Run red.**

Run: `php artisan test tests/Feature/Auth/StaffInvitationTest.php --filter="invitation mailable"`
Expected: FAIL (class missing).

- [ ] **Step 3: `app/Mail/StaffInvitationMail.php`.**

```php
<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $invitee,
        public User $inviter,
        public string $setupUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'دعوة لإنشاء حسابك في منظومة أرشفة مشاريع التخرج',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.staff-invitation',
            with: [
                'inviteeName' => $this->invitee->name,
                'inviterName' => $this->inviter->name,
                'setupUrl'    => $this->setupUrl,
            ],
        );
    }
}
```

- [ ] **Step 4: `resources/views/emails/staff-invitation.blade.php`** — self-contained inline-styled RTL HTML.

```blade
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Tajawal','Segoe UI',Tahoma,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                <tr><td style="background:#103A52;padding:28px 32px;text-align:center;">
                    <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="كلية التقنية الإلكترونية" height="56" style="display:inline-block;">
                </td></tr>
                <tr><td style="padding:32px;direction:rtl;text-align:right;">
                    <h1 style="margin:0 0 16px;font-size:20px;color:#103A52;">مرحباً {{ $inviteeName }}</h1>
                    <p style="margin:0 0 12px;line-height:1.9;font-size:15px;">
                        قام <strong>{{ $inviterName }}</strong> بدعوتك للانضمام إلى <strong>منظومة أرشفة مشاريع التخرج</strong>
                        الخاصة بكلية التقنية الإلكترونية — وهي المنظومة التي تُدار من خلالها أرشفة وتصنيف مشاريع التخرج.
                    </p>
                    <p style="margin:0 0 12px;line-height:1.9;font-size:15px;">
                        لإتمام إنشاء حسابك، يرجى الضغط على الزر أدناه لتعيين كلمة المرور الخاصة بك.
                        <strong>ينتهي هذا الرابط خلال 24 ساعة.</strong>
                    </p>
                    <p style="margin:0 0 24px;line-height:1.9;font-size:14px;color:#64748b;">
                        إذا لم تكن تتوقع هذه الرسالة، يمكنك تجاهلها أو التواصل مع مدير المنظومة.
                    </p>
                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;"><tr><td style="border-radius:8px;background:#1B6B93;">
                        <a href="{{ $setupUrl }}" style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">إنشاء كلمة المرور</a>
                    </td></tr></table>
                    <p style="margin:0 0 8px;font-size:13px;color:#64748b;">إذا لم يعمل الزر، انسخ الرابط التالي والصقه في المتصفح:</p>
                    <p style="margin:0;font-size:12px;color:#1B6B93;word-break:break-all;direction:ltr;text-align:left;">{{ $setupUrl }}</p>
                </td></tr>
                <tr><td style="background:#f8fafc;padding:16px 32px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;">
                    كلية التقنية الإلكترونية — طرابلس
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
```

- [ ] **Step 5: Run green.**

Run: `php artisan test tests/Feature/Auth/StaffInvitationTest.php`
Expected: PASS.

- [ ] **Step 6: Commit.**

```bash
git add app/Mail/StaffInvitationMail.php resources/views/emails/staff-invitation.blade.php tests/Feature/Auth/StaffInvitationTest.php
git commit -m "feat: Arabic RTL staff invitation mailable"
```

### Task E3: `SetupPasswordController` GET (verify link → form / friendly error)

**Files:**
- Modify: `routes/auth.php`
- Create: `app/Http/Controllers/Auth/SetupPasswordController.php`
- Create: `resources/js/pages/auth/SetupPassword.vue`
- Create: `resources/js/pages/auth/InvitationInvalid.vue`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Auth/StaffInvitationTest.php`

- [ ] **Step 1: Write failing tests** for the GET route.

```php
use App\Models\StaffInvitation;
use Illuminate\Support\Facades\URL;

function inviteUrlFor(User $user): string {
    $plain = StaffInvitation::issueFor($user);
    return StaffInvitation::signedUrlFor($user, $plain);
}

test('GET setup-password renders the form for a valid unused token', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create(['password' => null, 'is_active' => false, 'name' => 'Nadia F']);
    $user->assignRole('dept_staff');

    $this->get(inviteUrlFor($user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/SetupPassword')
            ->where('user.name', 'Nadia F')
            ->where('user.email', $user->email));
});

test('tampering the email in the URL does not render the form', function () {
    $user = User::factory()->create(['password' => null]);
    $url = inviteUrlFor($user);
    $tampered = str_replace(urlencode($user->email), urlencode('attacker@evil.test'), $url);

    $this->get($tampered)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
});

test('a validly-signed URL with an unknown token renders the error page', function () {
    $user = User::factory()->create(['password' => null]);
    StaffInvitation::issueFor($user);
    $bogus = URL::temporarySignedRoute('staff.setup-password', now()->addHour(), [
        'token' => 'totally-not-a-real-token', 'email' => $user->email,
    ]);

    $this->get($bogus)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
});

test('an expired invitation renders the error page', function () {
    $user = User::factory()->create(['password' => null]);
    $plain = StaffInvitation::issueFor($user);
    $url = URL::temporarySignedRoute('staff.setup-password', now()->addHour(), [
        'token' => $plain, 'email' => $user->email,
    ]);
    StaffInvitation::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

    $this->get($url)->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
});

test('a used invitation renders the error page even if not expired', function () {
    $user = User::factory()->create(['password' => null]);
    $plain = StaffInvitation::issueFor($user);
    $url = StaffInvitation::signedUrlFor($user, $plain);
    StaffInvitation::where('user_id', $user->id)->update(['used_at' => now()]);

    $this->get($url)->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
});

test('the setup route is not reachable by an authenticated session', function () {
    $user = User::factory()->create(['password' => null]);
    $url = inviteUrlFor($user);

    $this->actingAs(User::factory()->create())->get($url)->assertRedirect(route('dashboard'));
});
```

- [ ] **Step 2: Run red.**

Run: `php artisan test tests/Feature/Auth/StaffInvitationTest.php`
Expected: the new GET tests FAIL (route missing).

- [ ] **Step 3: Add routes to `routes/auth.php`** inside the `Route::middleware('guest')->group(...)`:

```php
    Route::get('setup-password/{token}', [\App\Http\Controllers\Auth\SetupPasswordController::class, 'create'])
        ->name('staff.setup-password');

    Route::post('setup-password/{token}', [\App\Http\Controllers\Auth\SetupPasswordController::class, 'store'])
        ->middleware('throttle:setup-password')
        ->name('staff.setup-password.store');
```

- [ ] **Step 4: `app/Http/Controllers/Auth/SetupPasswordController.php`** (GET half + shared resolver).

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\StaffInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class SetupPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        $invitation = $this->resolveValidInvitation($request, $token, 'view');

        if (! $invitation) {
            return Inertia::render('auth/InvitationInvalid');
        }

        $user = $invitation->user;

        return Inertia::render('auth/SetupPassword', [
            'token' => $token,
            'email' => $user->email,
            'submitUrl' => $request->fullUrl(),
            'user' => [
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->getRoleNames()->first(),
            ],
        ]);
    }

    /**
     * Returns the matching, still-valid StaffInvitation, or null on ANY failure
     * (bad signature, unknown/re-hashed token, email mismatch, expired, used,
     * or the account was already activated). Logs every attempt; never logs the token.
     */
    protected function resolveValidInvitation(Request $request, string $token, string $stage): ?StaffInvitation
    {
        $email = (string) $request->query('email', '');
        $ip = $request->ip();

        $fail = function (string $outcome, ?int $userId = null) use ($ip, $stage): null {
            Log::warning('staff setup-password attempt failed', [
                'stage' => $stage, 'outcome' => $outcome, 'user_id' => $userId, 'ip' => $ip,
            ]);
            return null;
        };

        if (! $request->hasValidSignature()) {
            return $fail('invalid_signature');
        }

        $invitation = StaffInvitation::where('token_hash', hash('sha256', $token))->first();
        if (! $invitation) {
            return $fail('unknown_token');
        }

        $user = $invitation->user;
        if (! $user || ! hash_equals($user->email, $email)) {
            return $fail('email_mismatch', $invitation->user_id);
        }
        if ($user->password !== null) {
            return $fail('already_activated', $user->id);
        }
        if ($invitation->isUsed()) {
            return $fail('used', $user->id);
        }
        if ($invitation->isExpired()) {
            return $fail('expired', $user->id);
        }

        return $invitation;
    }
}
```

- [ ] **Step 5: Register the rate limiter** in `app/Providers/AppServiceProvider.php::boot()`:

```php
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('setup-password', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perHour(5)->by($request->ip());
        });
    }
```

- [ ] **Step 6: `resources/js/pages/auth/InvitationInvalid.vue`.**

```vue
<script setup lang="ts">
import AuthBase from '@/layouts/AuthLayout.vue';
import TextLink from '@/components/TextLink.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase title="رابط غير صالح" description="تعذّر فتح رابط الدعوة">
        <Head title="رابط غير صالح" />
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400" dir="rtl">
            انتهت صلاحية الرابط أو استُخدم من قبل. اطلب من المدير إعادة إرسال دعوة جديدة.
        </div>
        <div class="mt-4 text-center text-sm">
            <TextLink :href="route('login')">العودة لتسجيل الدخول</TextLink>
        </div>
    </AuthBase>
</template>
```

- [ ] **Step 7: `resources/js/pages/auth/SetupPassword.vue`** — read-only identity block + password + confirmation.

```vue
<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const props = defineProps<{
    token: string;
    email: string;
    submitUrl: string;
    user: { name: string; email: string; role: string | null };
}>();

const roleLabels: Record<string, string> = {
    super_admin: 'مدير النظام', dept_manager: 'مدير القسم', supervisor: 'مشرف',
    dept_staff: 'موظف القسم', viewer: 'مشاهد',
};

const form = useForm({
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(props.submitUrl, { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <AuthBase title="إنشاء كلمة المرور" description="عيّن كلمة مرور لحسابك لإكمال التسجيل">
        <Head title="إنشاء كلمة المرور" />

        <div class="mb-4 rounded-lg border border-border bg-surface p-4 text-sm" dir="rtl">
            <p><span class="text-text-muted">الاسم:</span> <strong>{{ user.name }}</strong></p>
            <p><span class="text-text-muted">البريد الإلكتروني:</span> <strong>{{ user.email }}</strong></p>
            <p v-if="user.role"><span class="text-text-muted">الدور:</span> <strong>{{ roleLabels[user.role] ?? user.role }}</strong></p>
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-2">
                <Label for="password">كلمة المرور</Label>
                <Input id="password" type="password" required autofocus autocomplete="new-password" v-model="form.password" class="text-right" />
                <InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="password_confirmation">تأكيد كلمة المرور</Label>
                <Input id="password_confirmation" type="password" required autocomplete="new-password" v-model="form.password_confirmation" class="text-right" />
                <InputError :message="form.errors.password_confirmation" />
            </div>
            <Button type="submit" class="mt-2 w-full" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                إنشاء الحساب
            </Button>
        </form>
    </AuthBase>
</template>
```

- [ ] **Step 8: Run green.**

Run: `php artisan test tests/Feature/Auth/StaffInvitationTest.php` and `npm run build`
Expected: PASS; build clean.

- [ ] **Step 9: Commit.**

```bash
git add routes/auth.php app/Http/Controllers/Auth/SetupPasswordController.php app/Providers/AppServiceProvider.php resources/js/pages/auth/SetupPassword.vue resources/js/pages/auth/InvitationInvalid.vue tests/Feature/Auth/StaffInvitationTest.php
git commit -m "feat: setup-password link verification (GET) with friendly error page and rate limiter"
```

### Task E4: `SetupPasswordController` POST (set password → activate → login)

**Files:**
- Modify: `app/Http/Controllers/Auth/SetupPasswordController.php`
- Create: `app/Http/Requests/SetupPasswordRequest.php`
- Modify: `app/Http/Requests/Auth/LoginRequest.php`
- Test: `tests/Feature/Auth/StaffInvitationTest.php`

- [ ] **Step 1: Write failing tests.**

```php
test('a valid submit sets the password, activates the user, marks the token used, and logs in', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create(['password' => null, 'is_active' => false, 'name' => 'Sami K']);
    $user->assignRole('supervisor');
    $plain = StaffInvitation::issueFor($user);
    $url = StaffInvitation::signedUrlFor($user, $plain);

    $this->post($url, [
        'email' => $user->email,
        'password' => 'Str0ng-pass-9',
        'password_confirmation' => 'Str0ng-pass-9',
    ])->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->password)->not->toBeNull();
    expect($user->is_active)->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    expect(StaffInvitation::where('user_id', $user->id)->first()->used_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('reusing the URL after a successful setup shows the error page and does not re-login', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create(['password' => null, 'is_active' => false]);
    $user->assignRole('dept_staff');
    $plain = StaffInvitation::issueFor($user);
    $url = StaffInvitation::signedUrlFor($user, $plain);

    $this->post($url, ['email' => $user->email, 'password' => 'Str0ng-pass-9', 'password_confirmation' => 'Str0ng-pass-9']);
    auth()->logout();

    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
    $this->post($url, ['email' => $user->email, 'password' => 'Another-pass-1', 'password_confirmation' => 'Another-pass-1'])
        ->assertRedirect();
    $this->assertGuest();
});

test('the setup POST is rate limited to 5 per hour per IP', function () {
    $user = User::factory()->create(['password' => null]);
    $plain = StaffInvitation::issueFor($user);
    $url = StaffInvitation::signedUrlFor($user, $plain);
    StaffInvitation::where('user_id', $user->id)->update(['used_at' => now()]); // each attempt fails cleanly

    foreach (range(1, 5) as $i) {
        $this->post($url, ['email' => $user->email, 'password' => 'x', 'password_confirmation' => 'x']);
    }
    $this->post($url, ['email' => $user->email, 'password' => 'x', 'password_confirmation' => 'x'])
        ->assertStatus(429);
});

test('the invited user cannot log in before completing setup', function () {
    $user = User::factory()->create(['password' => null, 'is_active' => false, 'email' => 'locked@test.local']);

    $this->post('/login', ['email' => 'locked@test.local', 'password' => 'anything'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});
```

- [ ] **Step 2: Run red.**

Run: `php artisan test tests/Feature/Auth/StaffInvitationTest.php`
Expected: the new tests FAIL (`store()` not implemented; login gate absent).

- [ ] **Step 3: `app/Http/Requests/SetupPasswordRequest.php`.**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetupPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
```

- [ ] **Step 4: Add `store()` to `SetupPasswordController`.** (Add imports: `Illuminate\Http\RedirectResponse`, `Illuminate\Support\Facades\Auth`, `Illuminate\Support\Facades\Hash`, `Illuminate\Support\Str`, `App\Http\Requests\SetupPasswordRequest`.)

```php
    public function store(SetupPasswordRequest $request, string $token): RedirectResponse|Response
    {
        $invitation = $this->resolveValidInvitation($request, $token, 'submit');

        if (! $invitation) {
            return Inertia::render('auth/InvitationInvalid');
        }

        $user = $invitation->user;

        $user->forceFill([
            'password'          => Hash::make($request->validated()['password']),
            'is_active'         => true,
            'email_verified_at' => now(),
            'remember_token'    => Str::random(60),
        ])->save();

        $invitation->forceFill(['used_at' => now()])->save();

        Log::info('staff setup-password succeeded', ['user_id' => $user->id, 'ip' => $request->ip()]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', "مرحباً {$user->name}! تم إنشاء حسابك بنجاح.");
    }
```

- [ ] **Step 5: Add the `is_active` gate to `app/Http/Requests/Auth/LoginRequest.php`.** In `authenticate()`, after the successful-attempt block (right before `RateLimiter::clear($this->throttleKey());`), add:

```php
        if (! Auth::user()->is_active) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'هذا الحساب غير مُفعّل. يرجى إكمال دعوة إنشاء الحساب أو التواصل مع مدير المنظومة.',
            ]);
        }
```

- [ ] **Step 6: Run green.**

Run: `php artisan test tests/Feature/Auth/StaffInvitationTest.php tests/Feature/Auth/LoginTest.php tests/Feature/Auth/AuthenticationTest.php`
Expected: PASS. (Existing login tests use factory users with `is_active` true → unaffected.)

- [ ] **Step 7: Commit.**

```bash
git add app/Http/Controllers/Auth/SetupPasswordController.php app/Http/Requests/SetupPasswordRequest.php app/Http/Requests/Auth/LoginRequest.php tests/Feature/Auth/StaffInvitationTest.php
git commit -m "feat: complete setup-password submission, activate account, gate inactive logins"
```

### Task E5: Wire admin create + resend into the invite flow

**Files:**
- Modify: `app/Http/Requests/StoreUserRequest.php`, `app/Http/Controllers/Admin/UserController.php`, `routes/web.php`
- Test: `tests/Feature/Auth/StaffInvitationTest.php`, `tests/Feature/Admin/UserManagementTest.php`

- [ ] **Step 1: Write failing tests.**

```php
use App\Mail\StaffInvitationMail;
use Illuminate\Support\Facades\Mail;

test('super_admin creating a user queues a signed invitation email and locks the account', function () {
    Mail::fake();
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $admin = userWithRole('super_admin');
    $dept  = \App\Models\Department::factory()->create();

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Fresh Staff', 'email' => 'fresh@test.local',
        'employee_number' => 'EMP-777', 'role' => 'dept_manager', 'department_id' => $dept->id,
    ])->assertRedirect(route('admin.users.index'))->assertSessionHas('success');

    $user = \App\Models\User::where('email', 'fresh@test.local')->firstOrFail();
    expect($user->password)->toBeNull();
    expect((bool) $user->is_active)->toBeFalse();
    expect($user->hasRole('dept_manager'))->toBeTrue();
    expect(\App\Models\StaffInvitation::where('user_id', $user->id)->exists())->toBeTrue();

    Mail::assertSent(StaffInvitationMail::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email) && str_contains($mail->setupUrl, '/setup-password/') && str_contains($mail->setupUrl, 'signature=');
    });
});

test('creating a user with an existing email fails validation and creates nothing', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $admin = userWithRole('super_admin');
    User::factory()->create(['email' => 'dupe@test.local']);

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Dupe', 'email' => 'dupe@test.local', 'role' => 'viewer',
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'dupe@test.local')->count())->toBe(1);
});

test('resend-invitation issues a fresh token and invalidates the old one', function () {
    Mail::fake();
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $admin = userWithRole('super_admin');
    $user = User::factory()->create(['password' => null, 'is_active' => false]);
    $user->assignRole('dept_staff');
    $oldPlain = \App\Models\StaffInvitation::issueFor($user);
    $oldUrl = \App\Models\StaffInvitation::signedUrlFor($user, $oldPlain);

    $this->actingAs($admin)->post(route('admin.users.resend-invitation', $user))
        ->assertRedirect()->assertSessionHas('success');

    $this->get($oldUrl)->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
    Mail::assertSent(StaffInvitationMail::class);
});

test('resend-invitation is rejected for an already-activated user', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $admin = userWithRole('super_admin');
    $active = User::factory()->create();
    $active->assignRole('viewer');

    $this->actingAs($admin)->post(route('admin.users.resend-invitation', $active))
        ->assertSessionHasErrors();
});

test('non-super_admin cannot create a user or resend an invitation', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $manager = userWithRole('dept_manager');
    $target = User::factory()->create(['password' => null]);

    $this->actingAs($manager)->post(route('admin.users.store'), [
        'name' => 'x', 'email' => 'x@test.local', 'role' => 'viewer',
    ])->assertForbidden();

    $this->actingAs($manager)->post(route('admin.users.resend-invitation', $target))->assertForbidden();
});
```

- [ ] **Step 2: Run red.**

Run: `php artisan test tests/Feature/Auth/StaffInvitationTest.php`
Expected: FAIL.

- [ ] **Step 3: `StoreUserRequest`** — remove the `'password'` rule entirely. Keep `name`, `email` (`unique:users,email`), `employee_number`, `role`, `department_id`. Drop `is_active` (forced false in the controller).

- [ ] **Step 4: `UserController::store()` + `resendInvitation()` + `sendInvitation()`.**

```php
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name'            => $validated['name'],
            'email'           => $validated['email'],
            'password'        => null,
            'employee_number' => $validated['employee_number'] ?? null,
            'department_id'   => $validated['department_id'] ?? null,
            'is_active'       => false,
        ]);

        $user->assignRole($validated['role']);

        $this->sendInvitation($user, $request->user());

        return redirect()->route('admin.users.index')
            ->with('success', 'تم إنشاء المستخدم وإرسال دعوة إنشاء الحساب إلى بريده الإلكتروني');
    }

    public function resendInvitation(Request $request, User $user)
    {
        if ($user->password !== null) {
            return back()->withErrors(['invitation' => 'هذا الحساب مُفعّل بالفعل ولا يحتاج إلى دعوة']);
        }

        $this->sendInvitation($user, $request->user());

        return back()->with('success', 'تم إرسال دعوة جديدة إلى ' . $user->email);
    }

    protected function sendInvitation(User $user, User $inviter): void
    {
        $plain = \App\Models\StaffInvitation::issueFor($user);
        $url = \App\Models\StaffInvitation::signedUrlFor($user, $plain);

        \Illuminate\Support\Facades\Mail::to($user->email)
            ->send(new \App\Mail\StaffInvitationMail($user, $inviter, $url));
    }
```

- [ ] **Step 5: Route** — in `routes/web.php`, inside the `role:super_admin` `admin` group (after the `toggle-active` line):

```php
        Route::post('users/{user}/resend-invitation', [AdminUserController::class, 'resendInvitation'])
            ->name('users.resend-invitation');
```

- [ ] **Step 6: Adjust `UserManagementTest` "super_admin can create user with role"** — add `\Illuminate\Support\Facades\Mail::fake();` at the top of that test; drop the `password`/`is_active` keys from the POST body (now ignored). Its assertions still hold. Leave the other 8 tests unchanged.

- [ ] **Step 7: Run green.**

Run: `php artisan test tests/Feature/Auth/StaffInvitationTest.php tests/Feature/Admin/UserManagementTest.php`
Expected: PASS.

- [ ] **Step 8: Commit.**

```bash
git add app/Http/Requests/StoreUserRequest.php app/Http/Controllers/Admin/UserController.php routes/web.php tests/Feature/Auth/StaffInvitationTest.php tests/Feature/Admin/UserManagementTest.php
git commit -m "feat: admin user creation sends signed email invite; add resend-invitation"
```

### Task E6: Admin Users UI — drop password field, add resend action, pending badge

**Files:**
- Modify: `resources/js/pages/Admin/Users/Index.vue`
- Modify: `app/Http/Controllers/Admin/UserController.php` (expose `has_password` on the index payload)

- [ ] **Step 1: `UserController::index()`** — replace `$paginated->items()` in the `'data' =>` key with a mapped list adding `has_password`:

```php
        $items = collect($paginated->items())->map(function (User $u) {
            $arr = $u->toArray();
            $arr['has_password'] = $u->password !== null;
            return $arr;
        })->all();
```

- [ ] **Step 2: Create modal.** Remove the "كلمة المرور" `<div>` block from `#create-user-form` and `password: ''` from `createForm`. Remove the `is_active` checkbox block and `is_active: true` from `createForm`. Header button label `+ إضافة مستخدم` → `+ إضافة موظف جديد`. Modal `title` → `إضافة موظف جديد`.

- [ ] **Step 3: Edit modal unchanged** (inline password reset for an activated user still valid; `UpdateUserRequest`/`update()` untouched).

- [ ] **Step 4: `UserItem` interface** — add `has_password: boolean;`. Status cell: when `!user.has_password` show an amber `بانتظار التفعيل` badge instead of the نشط/غير نشط badge:

```vue
<td class="px-4 py-3 text-sm">
    <span v-if="!user.has_password" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/20 dark:text-amber-400">بانتظار التفعيل</span>
    <span v-else :class="user.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400'" class="rounded-full px-2.5 py-0.5 text-xs font-medium">
        {{ user.is_active ? 'نشط' : 'غير نشط' }}
    </span>
</td>
```

- [ ] **Step 5: Resend row-action.** In the actions cell, before the delete button:

```vue
<button
    v-if="!user.has_password"
    type="button"
    class="rounded bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 hover:bg-blue-200 dark:bg-blue-900/20 dark:text-blue-400"
    @click="resendInvite(user)"
>
    إعادة إرسال الدعوة
</button>
```

Wrap the "تعديل" and "إيقاف/تفعيل" buttons in `v-if="user.has_password"` (nothing to edit/toggle before activation). Script:

```ts
function resendInvite(user: UserItem) {
    router.post(route('admin.users.resend-invitation', user.id), {}, { preserveScroll: true });
}
```

- [ ] **Step 6: Build + test.**

Run: `npm run build && php artisan test tests/Feature/Admin tests/Feature/Auth`
Expected: build clean; tests green.

- [ ] **Step 7: Commit.**

```bash
git add resources/js/pages/Admin/Users/Index.vue app/Http/Controllers/Admin/UserController.php
git commit -m "feat: admin users UI — invite-only creation, resend action, pending-activation badge"
```

### Task E7: Docs for Fix 5

**Files:**
- Modify: `CLAUDE.md`, `PROGRESS.md`

- [ ] **Step 1: `PROGRESS.md`** — Section 2: add `staff_invitations` table entry (id, user_id FK cascade, token_hash varchar(64) index, expires_at, used_at nullable, created_at nullable); note `users.password` is now nullable. Section 3: add `StaffInvitation` model. Section 4: add `SetupPasswordController`. Add a dated `Major Changes` bullet (2026-08-29) covering the invite flow (signed route + sha256-hashed token + single-use + 24h), rate limiter, `is_active` login gate, `employee_number` rename, and the search widening.

- [ ] **Step 2: `CLAUDE.md`** — "Current Status": add a `✅ Phase 1 Closeout — 5 fixes` bullet summarising all five. Note `users` is a staff-only table with `employee_number`; account activation is invite-only (super_admin cannot set a password directly).

- [ ] **Step 3: Commit.**

```bash
git add CLAUDE.md PROGRESS.md
git commit -m "docs: record Phase 1 closeout fixes (invite flow, employee_number, search, UI)"
```

---

## PART F — Verification

### Task F1: Full suite + build + style

- [ ] **Step 1:** `php artisan migrate:fresh` against dev MariaDB (XAMPP MySQL running) to prove the migration chain end-to-end; then `php artisan migrate:fresh --seed`. (If MySQL is down, note it — the SQLite test run already exercises the chain via `RefreshDatabase`.)
- [ ] **Step 2:** `npm run build` then `php artisan test` — expect 261 baseline + new tests (~2 browse + 6 internal search + ~4 rename + ~20 invite) ≈ 293+, all green, 0 failures.
- [ ] **Step 3:** `npm run build` — clean.
- [ ] **Step 4:** `vendor/bin/pint --dirty` — commit if it reformats anything.

### Task F2: Playwright walkthrough (real app, port ≠ 8000, real Mailpit)

- [ ] **Step 1:** Start Mailpit; `php artisan serve --port=8001`; `npm run dev` (or serve built assets); seed a super_admin (`php artisan db:seed --class=AdminSeeder` or DummyDataSeeder).
- [ ] **Step 2:** Screenshots into `docs/walkthrough/2026-08-29-phase1-closeout/`:
  1. Landing page — no login button anywhere.
  2. Authenticated sidebar (super_admin + one other role) — no `تصفح المشاريع`.
  3. `/search` finding a project via a student name (internal) + `/browse` NOT finding a pending-proposal-only.
  4. `/admin/users` showing the `الرقم الوظيفي` column + label.
  5. Full invite flow: create user in admin → Mailpit inbox screenshot of the invite → click link → setup form screenshot → submit → logged-in dashboard screenshot.

### Task F3: Code review + PR

- [ ] **Step 1:** `/code-review` on `main...worktree-fix+phase1-closeout`. Fix real findings; re-run the suite.
- [ ] **Step 2:** Push; open a PR against `main` (per-fix summary, the `registration_number` grep split, test/build results, walkthrough screenshots, flagged ambiguities). **Do not merge.** Report the PR URL.

---

## Self-Review

**Spec coverage:**
- Fix 1 → Part A (all three CTAs, per user decision). ✓
- Fix 2 → Part B (all 5 menus + `BookOpen` import). ✓
- Fix 3 → Part C: wide fields on both surfaces (C1 helpers + public browse), unified internal proposals∪projects with badges + correct routes (C2), Vue render (C3); 6 spec-listed tests + 2 browse tests. `detectSimilarity` title-only ✓; single unified input ✓; trim+lowercase server-side ✓; OR-across-fields ✓; public browse still archived-projects-only ✓.
- Fix 4 → Part D: standalone reversible migration (D1); model/factory/requests/controller (D2); frontend + docs + test realignment (D3); grep split documented; `proposal_students` untouched ✓; original migration untouched ✓; factory `employee_number` test ✓.
- Fix 5 → Part E: dedicated `staff_invitations` table (E1) with sha256-hashed token + nullable password; Arabic RTL mailable (E2); signed-link GET verify + friendly error + guest-only + rate limiter (E3); POST activate+login+verify-email + `is_active` login gate (E4); admin create→invite + resend + duplicate-email guard + 403s (E5); UI: no password field / resend row action / pending badge (E6); docs (E7). All 12 spec test bullets map to E3/E4/E5. Signed route ✓; hashed at rest ✓; rate limit 5/h ✓; audit logging without token ✓; guest middleware ✓; reuse-after-success friendly error ✓; no manual password on creation ✓; existing forgot-password flow untouched ✓.
- Sequencing 1,2 → 3 → 4 → 5; two isolated `users`-touching migrations (rename + nullable password). ✓
- Verification standards → Part F. ✓

**Placeholder scan:** No TBD/TODO; every code step has real content; tests have real assertions. ✓

**Type consistency:** `searchInternal` returns `LengthAwarePaginator` of the array shape consumed in C3 (`results.data[].{type,entity_label,route,route_id,…}`). `StaffInvitation::issueFor` → plain string; `signedUrlFor(User,string)` used consistently E1/E3/E4/E5. `resolveValidInvitation(Request,string,string)` used by GET and POST. `has_password` added in E6 backend + interface. `StaffInvitationMail` public props `$invitee/$inviter/$setupUrl` match the `Mail::assertSent` closure in E5. ✓

**Flagged for the final report (not resolved here):**
1. Project is Laravel **12.62**, not 11 — spec and `CLAUDE.md` say "Laravel 11". Context7 docs consulted were 13.x (API-identical for signed routes / renameColumn / Mailables as used). Note only.
2. **Mailpit is not running** in this environment — tests don't need it (`array` mailer); the walkthrough does. Fix: start `mailpit` (binary) or `docker run -p1025:1025 -p8025:8025 axllent/mailpit`. No `.env` change needed (already `smtp`/`127.0.0.1`/`1025`).
3. `UserFactory` had **no** `registration_number` — Fix 4 adds `employee_number` (unique `EMP-######`) to satisfy the "factory produces employee_number" test. Low risk (mirrors the `email` unique pattern).
4. `is_active` login gate (E4 step 5) is slightly beyond the literal 5-fix scope but was confirmed with the user; closes a real hole (deactivated users could previously still authenticate).
5. The worktree needs `public/build/manifest.json` for the ~70 full-stack Inertia render tests to pass. `npm run build` has been run; baseline is 261/0. Re-build after every batch of Vue edits and before any full `php artisan test`.
