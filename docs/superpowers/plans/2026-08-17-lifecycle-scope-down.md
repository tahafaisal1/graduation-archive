# Project Lifecycle Scope-Down Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the planned 8-status/11-stage project lifecycle with a 2-status model ("مقترح" / "مؤرشف") plus two approval-gate fields (supervisor, department) tracked directly on `projects`, without breaking any working Phase 1 feature.

**Architecture:** Add four nullable approval-gate columns to `projects` via a new migration (never edit the original migration). Shrink `ProjectStatus` seed data to exactly 2 rows. Add fillable/casts/relationships/helpers to the `Project` model. Update `DummyDataSeeder` so no seeded project ever references a status outside {1,2}, then rebuild the dev DB with `migrate:fresh --seed`. Write a new Pest feature test file first (TDD), then run the *entire* existing suite to confirm no regression. STATUS_HISTORY, DEFENSE, and PROJECT_DOCUMENT are audited but not touched (see Task 1 findings — only `project_documents` exists, and it's actively used, so it's explicitly out of scope for removal).

**Tech Stack:** Laravel 12 (composer.json), PHP 8.2, Pest 3.8, MariaDB 10.4 (via XAMPP), Spatie laravel-permission.

**Spec:** User's task instructions (Steps 0-8), this session's conversation.

## Global Constraints

- Never edit `database/migrations/2026_06_15_100005_create_projects_table.php` or `2026_06_15_100002_create_project_status_table.php` directly — additive migration only.
- `is_deleted` boolean stays the soft-delete mechanism; do not introduce `deleted_at`.
- No REST API endpoints; Inertia responses only (not touched by this change anyway).
- English identifiers in code, Arabic only in seeded/UI text.
- Full existing Pest suite (`php artisan test`) must pass before Step 7 (docs) — do not proceed past a pre-existing regression without root-causing it (superpowers:systematic-debugging).
- Work happens in worktree `.claude/worktrees/lifecycle-scope-down` on branch `worktree-lifecycle-scope-down` — never commit to `main`.

## Audit Findings (Step 1 — already completed before this plan was written)

- `project_status` currently seeded with **10 rows** (only id=1 `archived` has `is_active=true`; ids 2-10 are Phase-2 placeholders never surfaced in UI).
- Live dev DB (`graduation_archive`, started via `C:\xampp\mysql_start.bat`) currently has 26 seeded projects: 22 at status 1 (archived), 1 at status 2 (proposal_submitted), 2 at status 5 (in_progress), 1 at status 6 (ready_for_defense). All from `DummyDataSeeder` — disposable demo data, not real submissions.
- **User decision (asked directly, not assumed):** update `DummyDataSeeder` to only ever use status_id 1 or 2, then run `php artisan migrate:fresh --seed` to rebuild — this is Task 4 below.
- Grepped the whole codebase for `STATUS_HISTORY`/`status_history`, `DEFENSE`/`defense`, `PROJECT_DOCUMENT`/`project_document`:
  - **STATUS_HISTORY**: no migration, no model, no controller reference anywhere. Only ever existed in CLAUDE.md/PROGRESS.md planning text. Never built. **Nothing to remove.**
  - **DEFENSE**: same — no migration, no model, zero code references. `defense` string matches are only status *names* inside `ProjectStatusSeeder` (`ready_for_defense`, `under_defense`) and their Arabic labels in Vue — not a separate table/feature. Never built. **Nothing to remove.**
  - **PROJECT_DOCUMENT**: **DOES exist** — `database/migrations/2026_06_15_100007_create_project_documents_table.php`, `app/Models/ProjectDocument.php`, actively used by `ProjectController::store()`/`update()` (creates a document row on every PDF upload) and displayed in `Projects/Show.vue`. **Explicitly out of scope for removal** — it's a working Phase 1 feature (milestone document tracking), not a Phase-2 placeholder.
- Only two `status_name` values are referenced anywhere in Vue UI code (`Projects/Index.vue`, `Projects/Show.vue`): `archived` and `proposal_submitted`. The other 8 status names are dead in the frontend already — confirms narrowing to 2 rows breaks no UI code.
- `ProjectController` already hardcodes `STATUS_ARCHIVED = 1` / `STATUS_PENDING = 2` as class constants — the 2-status model is already implicitly assumed by the app logic. This change formalizes that.

Conclusion: **Step 4 (remove unused planned tables) is a no-op** — skipped, documented as such in Task 6 (docs update).

**Second stop-and-report finding (discovered while implementing Task 2):** The task's Step 3 text literally assigns `status_id 1 = "مقترح"` (proposal) and `status_id 2 = "مؤرشف"` (archived). But the entire existing codebase hardcodes the opposite meaning for those IDs: `ProjectController::STATUS_ARCHIVED = 1` / `STATUS_PENDING = 2`, five `current_status_id = 1` "archived/public" filters in `PublicController`, `SearchService`, `ReportService`'s `pending_approvals` (status_id 2), `ProjectsImport`, Vue `canApprove` gates (`status_name === 'proposal_submitted'`, today id 2), and several Pest tests. Asked the user directly; **decision: keep the existing ID assignment** — `id=1` stays "مؤرشف" (archived), `id=2` stays "مقترح" (proposal) — only the Arabic `status_name` text and `sort_order` (workflow order, unused elsewhere in the app) change, not which ID means what. This requires zero changes to `ProjectController`/`PublicController`/`SearchService`/`ReportService`/`ProjectsImport` or their tests. Tasks 2-6 below reflect this corrected mapping.

---

### Task 1: Migration — approval gate fields on `projects`

**Files:**
- Create: `database/migrations/2026_08_17_000001_add_approval_gates_to_projects_table.php`

**Interfaces:**
- Produces: columns `supervisor_approved_by` (nullable FK → users.id, nullOnDelete), `supervisor_approved_at` (nullable timestamp), `department_approved_by` (nullable FK → users.id, nullOnDelete), `department_approved_at` (nullable timestamp) on `projects`. Later tasks (Model, tests) rely on these exact column names.

- [ ] **Step 1: Create the migration file**

```bash
php artisan make:migration add_approval_gates_to_projects_table --table=projects
```

- [ ] **Step 2: Write the migration**

Context7 (`/laravel/docs`, migrations.md) confirms the current syntax: call `nullable()` before `constrained()` on a `foreignId()` column.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('supervisor_approved_by')
                ->nullable()
                ->after('current_status_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('supervisor_approved_at')->nullable()->after('supervisor_approved_by');

            $table->foreignId('department_approved_by')
                ->nullable()
                ->after('supervisor_approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('department_approved_at')->nullable()->after('department_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_approved_by');
            $table->dropColumn('supervisor_approved_at');
            $table->dropConstrainedForeignId('department_approved_by');
            $table->dropColumn('department_approved_at');
        });
    }
};
```

- [ ] **Step 3: Run the migration**

Run: `php artisan migrate`
Expected: `add_approval_gates_to_projects_table` migrated successfully, no errors.

- [ ] **Step 4: Verify columns exist**

Run: `php artisan tinker --execute="echo implode(',', Schema::getColumnListing('projects'));"`
Expected: output includes `supervisor_approved_by,supervisor_approved_at,department_approved_by,department_approved_at`.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_17_000001_add_approval_gates_to_projects_table.php
git commit -m "feat: add supervisor/department approval gate columns to projects"
```

---

### Task 2: Update ProjectStatusSeeder to 2 rows

**Files:**
- Modify: `database/seeders/ProjectStatusSeeder.php`
- Modify: `tests/Feature/Seeders/ProjectStatusSeederTest.php`

**Interfaces:**
- Produces: `project_status` table with exactly `{id:1, status_name:'مؤرشف', sort_order:2, is_active:true}` and `{id:2, status_name:'مقترح', sort_order:1, is_active:true}` after `db:seed` — id assignment kept as-is (see corrected finding above); only the Arabic text and sort_order (workflow order) changed.

**Pre-existing test that must be updated, not treated as a regression:** `tests/Feature/Seeders/ProjectStatusSeederTest.php` currently asserts `ProjectStatus::count()->toBe(10)`, a status named `'archived'` with `is_active=true`, and 9 others with `is_active=false`. These assertions directly encode the old 10-status spec this task intentionally replaces — grepped the whole `tests/` directory and confirmed this is the *only* file with such hardcoded assumptions (no other test references `ProjectStatus::count()`, `'archived'`, or `'proposal_submitted'` as literal strings). Update it in this same task so Task 5 Step 4's full-suite run doesn't surface an expected, already-understood failure as if it were a surprise regression.

- [ ] **Step 1: Rewrite the seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\ProjectStatus;
use Illuminate\Database\Seeder;

class ProjectStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 1, 'status_name' => 'مؤرشف',  'sort_order' => 2, 'is_active' => true],
            ['id' => 2, 'status_name' => 'مقترح',  'sort_order' => 1, 'is_active' => true],
        ];

        foreach ($statuses as $status) {
            ProjectStatus::updateOrCreate(['id' => $status['id']], $status);
        }
    }
}
```

Note: this seeder alone does NOT delete pre-existing rows 3-10 from a live DB (it only `updateOrCreate`s ids 1-2) — that cleanup happens via `migrate:fresh` in Task 4, per the user's chosen "fresh reseed" resolution to the Step-1 audit finding.

- [ ] **Step 2: Rewrite the seeder test to match the new 2-status spec**

```php
<?php

use App\Models\ProjectStatus;
use Database\Seeders\ProjectStatusSeeder;

beforeEach(function () {
    $this->seed(ProjectStatusSeeder::class);
});

test('exactly 2 statuses exist in database', function () {
    expect(ProjectStatus::count())->toBe(2);
});

test('مؤرشف status is id 1 and is active', function () {
    $archived = ProjectStatus::find(1);

    expect($archived)->not->toBeNull()
        ->and($archived->status_name)->toBe('مؤرشف')
        ->and($archived->is_active)->toBeTrue();
});

test('مقترح status is id 2 and is active', function () {
    $proposal = ProjectStatus::find(2);

    expect($proposal)->not->toBeNull()
        ->and($proposal->status_name)->toBe('مقترح')
        ->and($proposal->is_active)->toBeTrue();
});
```

- [ ] **Step 3: Run this test file alone to confirm it passes**

Run: `php artisan test tests/Feature/Seeders/ProjectStatusSeederTest.php`
Expected: PASS, 3 tests, 0 failures.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/ProjectStatusSeeder.php tests/Feature/Seeders/ProjectStatusSeederTest.php
git commit -m "feat: narrow project_status seed data to 2 statuses (مقترح, مؤرشف)"
```

(No `down`/no destructive action against the live DB happens in this task — reseed happens together with Task 4's DummyDataSeeder fix.)

- [ ] **Step 5: Update Vue status maps in Projects/Index.vue and Projects/Show.vue**

Both files hardcode `STATUS_COLORS`/`STATUS_LABELS` keyed by the old English `status_name` values (`archived`, `proposal_submitted`, plus 8 dead Phase-2 keys), and both have a `canApprove` check `status_name === 'proposal_submitted'` gating the Approve button. Since `status_name` is now the Arabic text itself, update both files:

In `resources/js/pages/Projects/Index.vue`, replace the `canApprove` check:
```ts
function canApprove(p: Project) {
    return ['dept_manager', 'super_admin'].includes(userRole.value)
        && p.current_status?.status_name === 'مقترح';
}
```

Replace `STATUS_COLORS` and `STATUS_LABELS`:
```ts
const STATUS_COLORS: Record<string, string> = {
    'مؤرشف': 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    'مقترح': 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
};

const STATUS_LABELS: Record<string, string> = {
    'مؤرشف': 'مؤرشف',
    'مقترح': 'في انتظار الموافقة',
};
```

Apply the identical `canApprove`, `STATUS_COLORS`, and `STATUS_LABELS` replacements to `resources/js/pages/Projects/Show.vue` (its `canApprove` is a `computed()`, keep that shape — only the string literal inside changes from `'proposal_submitted'` to `'مقترح'`).

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Projects/Index.vue resources/js/pages/Projects/Show.vue
git commit -m "fix: match Vue status badge/approval-gate logic to new Arabic status_name values"
```

---

### Task 3: Update the Project model

**Files:**
- Modify: `app/Models/Project.php`

**Interfaces:**
- Consumes: columns from Task 1 (`supervisor_approved_by`, `supervisor_approved_at`, `department_approved_by`, `department_approved_at`).
- Produces: `Project::supervisorApprovedBy(): BelongsTo`, `Project::departmentApprovedBy(): BelongsTo`, `Project::isSupervisorApproved(): bool`, `Project::isDepartmentApproved(): bool` — Task 5 tests call these exact names.

- [ ] **Step 1: Add fillable fields**

In `$fillable`, after `'based_on_project_id',` add:

```php
        'supervisor_approved_by',
        'supervisor_approved_at',
        'department_approved_by',
        'department_approved_at',
```

- [ ] **Step 2: Add casts**

In the `casts()` method, add to the returned array:

```php
            'supervisor_approved_at'  => 'datetime',
            'department_approved_at'  => 'datetime',
```

- [ ] **Step 3: Add relationships and helpers**

After the existing `basedOn()` method, add:

```php
    public function supervisorApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_approved_by');
    }

    public function departmentApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_approved_by');
    }

    public function isSupervisorApproved(): bool
    {
        return $this->supervisor_approved_at !== null;
    }

    public function isDepartmentApproved(): bool
    {
        return $this->department_approved_at !== null;
    }
```

- [ ] **Step 4: Commit**

```bash
git add app/Models/Project.php
git commit -m "feat: add approval gate fields, relationships, and helpers to Project model"
```

(Full verification of this task happens together with Task 5's tests — no standalone tinker check needed since the fields aren't queryable yet without Task 1's migration, which is already applied.)

---

### Task 4: Fix DummyDataSeeder status references + fresh reseed

**Files:**
- Modify: `database/seeders/DummyDataSeeder.php`

**Interfaces:**
- Consumes: `ProjectStatus` rows from Task 2 (only ids 1, 2 exist after reseed).

- [ ] **Step 1: Change the 3 out-of-range status values**

In the `$projectDefs` array inside `createProjects()`, three rows use `'status' => 5` or `'status' => 6`:
- Line ~205: `'بوابة التعليم الإلكتروني للمدارس الثانوية'` — `'status' => 5` → change to `'status' => 2`
- Line ~222: `'تحسين أداء الشبكات السحابية الموزعة'` — `'status' => 5` → change to `'status' => 2`
- Line ~229: `'نظام مراقبة الطاقة الشمسية في المناطق النائية'` — `'status' => 6` → change to `'status' => 2`

These three already have `'score' => null`, consistent with being non-archived/pending projects — no other field changes needed.

- [ ] **Step 2: Verify no other status values outside {1,2} remain**

Run: `grep -n "'status' =>" database/seeders/DummyDataSeeder.php`
Expected: every value is `1` or `2`.

- [ ] **Step 3: Rebuild the dev database**

Run: `php artisan migrate:fresh --seed`
Expected: all migrations (including Task 1's) + `DatabaseSeeder` run clean, no FK errors.

- [ ] **Step 4: Verify only 2 statuses exist and all projects reference them**

Run: `php artisan tinker --execute="echo App\Models\Project::selectRaw('current_status_id, count(*) as c')->groupBy('current_status_id')->get()->toJson(); echo App\Models\ProjectStatus::count();"`
Expected: only `current_status_id` 1 and 2 appear; `ProjectStatus::count()` is 2.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/DummyDataSeeder.php
git commit -m "fix: keep DummyDataSeeder project statuses within the new 2-status range"
```

---

### Task 5: TDD — ProjectApprovalTest

**Files:**
- Create: `tests/Feature/Project/ProjectApprovalTest.php`

**Interfaces:**
- Consumes: `Project` model fields/helpers from Task 3, `ProjectStatus` seed data from Task 2, existing `userWithRole()` Pest helper (`tests/Pest.php`), existing `ProjectFactory`/`DepartmentFactory`/`SpecializationFactory`.

- [ ] **Step 1: Write the failing test file**

First inspect existing factory usage patterns so the new test matches conventions — read `database/factories/ProjectFactory.php` and one existing test (e.g. `tests/Feature/Models/ProjectModelTest.php`) before writing, to reuse the same factory call shape rather than guessing field names.

```php
<?php

use App\Models\Department;
use App\Models\Project;
use App\Models\Specialization;
use App\Models\User;

test('a new project defaults to status 2 (مقترح) with both approvals null', function () {
    $department = Department::factory()->create();
    $specialization = Specialization::factory()->for($department)->create();
    $supervisor = userWithRole('supervisor');

    $project = Project::factory()->create([
        'department_id' => $department->id,
        'specialization_id' => $specialization->id,
        'supervisor_id' => $supervisor->id,
        'current_status_id' => 2,
    ]);

    expect($project->current_status_id)->toBe(2)
        ->and($project->currentStatus->status_name)->toBe('مقترح')
        ->and($project->supervisor_approved_by)->toBeNull()
        ->and($project->supervisor_approved_at)->toBeNull()
        ->and($project->department_approved_by)->toBeNull()
        ->and($project->department_approved_at)->toBeNull()
        ->and($project->isSupervisorApproved())->toBeFalse()
        ->and($project->isDepartmentApproved())->toBeFalse();
});

test('a project with both approvals filled can be moved to status 1 (مؤرشف)', function () {
    $department = Department::factory()->create();
    $specialization = Specialization::factory()->for($department)->create();
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager');

    $project = Project::factory()->create([
        'department_id' => $department->id,
        'specialization_id' => $specialization->id,
        'supervisor_id' => $supervisor->id,
        'current_status_id' => 2,
    ]);

    $project->update([
        'supervisor_approved_by' => $supervisor->id,
        'supervisor_approved_at' => now(),
        'department_approved_by' => $manager->id,
        'department_approved_at' => now(),
    ]);
    $project->refresh();

    expect($project->isSupervisorApproved())->toBeTrue()
        ->and($project->isDepartmentApproved())->toBeTrue()
        ->and($project->supervisorApprovedBy->id)->toBe($supervisor->id)
        ->and($project->departmentApprovedBy->id)->toBe($manager->id);

    $project->update(['current_status_id' => 1]);
    $project->refresh();

    expect($project->current_status_id)->toBe(1)
        ->and($project->currentStatus->status_name)->toBe('مؤرشف');
});
```

- [ ] **Step 2: Run to verify it fails (before Tasks 1-4 are applied, or if run out of order)**

Run: `php artisan test tests/Feature/Project/ProjectApprovalTest.php`
Expected: FAIL — either "Unknown column 'supervisor_approved_by'" (if Task 1 not yet applied) or status_name mismatch (if Task 2/4 not applied). Since Tasks 1-4 execute before this task in the plan, if everything above was done correctly this may already PASS on first run — in that case, temporarily comment out one assertion (e.g. `isSupervisorApproved()`), confirm the test fails, then restore it, to prove the test actually exercises real behavior (satisfies TDD's fail-first requirement without artificially reordering the plan).

- [ ] **Step 3: Run to verify it passes**

Run: `php artisan test tests/Feature/Project/ProjectApprovalTest.php`
Expected: PASS, 2 tests, 0 failures.

- [ ] **Step 4: Run the FULL existing suite**

Run: `php artisan test`
Expected: all pre-existing tests still pass (baseline was 208/218 passing with 10 pre-existing ext-zip failures per PROGRESS.md — confirm the same 10 (and only those 10) fail, nothing new breaks). If any test outside the known ext-zip import failures fails, STOP and invoke superpowers:systematic-debugging — do not patch symptoms, do not proceed to Task 6.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Project/ProjectApprovalTest.php
git commit -m "test: add ProjectApprovalTest for 2-status lifecycle + approval gates"
```

---

### Task 6: Update CLAUDE.md and PROGRESS.md

**Files:**
- Modify: `CLAUDE.md`
- Modify: `PROGRESS.md`

**Interfaces:**
- None (documentation only).

- [ ] **Step 1: Update CLAUDE.md**

In the "Database — 13 Tables" / Phase 2 section and "Key Business Rules", replace the 11-stage/8-status lifecycle language with:
- `project_status` has exactly 2 rows: مقترح (proposal, id=1) and مؤرشف (archived, id=2).
- Supervisor approval and department approval are tracked as `supervisor_approved_by/_at` and `department_approved_by/_at` columns on `projects`, not as separate lifecycle statuses.
- Remove `student_eligibility`, `defense`, `status_history` from the "Phase 2 (Future)" table list; explicitly note STATUS_HISTORY, DEFENSE, and PROJECT_DOCUMENT-as-milestone-tracking-expansion are out of scope for this system's scope (PROJECT_DOCUMENT itself already exists and stays, only its *expansion* into a full milestone-tracking lifecycle is out of scope).

- [ ] **Step 2: Update PROGRESS.md**

Add a new "Current Status" entry (following the existing format of prior entries) documenting:
- New migration `2026_08_17_000001_add_approval_gates_to_projects_table` and its 4 columns.
- `project_status` reseeded to 2 rows (مقترح/مؤرشف) with exact values.
- `Project` model additions (fillable, casts, relationships, helper methods) with exact method names.
- `DummyDataSeeder` fix (3 projects reassigned from status 5/6 to status 2).
- Explicit statement: STATUS_HISTORY and DEFENSE were never implemented (audit-confirmed, planning-only); PROJECT_DOCUMENT exists and remains in scope as-is (milestone document tracking), unrelated to the removed lifecycle statuses.
- Test file added: `tests/Feature/Project/ProjectApprovalTest.php`, and updated total suite pass count from the Task 5 Step 4 run.
- Update Section 2's `project_status` table documentation (currently lists 10 seeded rows) to reflect the new 2-row reality.
- Update Section 2's `projects` table documentation to list the 4 new columns.
- Update Section 3's `Project` model documentation (fillable/casts/relationships) to match Task 3.

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md PROGRESS.md
git commit -m "docs: reflect 2-status lifecycle and approval-gate fields in CLAUDE.md/PROGRESS.md"
```

---

### Task 7: Self-review

**Files:** none (verification only)

- [ ] **Step 1: superpowers:verification-before-completion**

Re-run `php artisan test` fresh (not from memory/cache of Task 5's run) and capture the exact pass/fail count to quote in the final report. Re-check `git diff main...HEAD --stat` to confirm only the intended files changed.

- [ ] **Step 2: /code-review on the branch diff**

Run the code-review skill against the diff between `main` and the current branch. Record every finding.

- [ ] **Step 3: Fix real issues, note disagreements**

Apply fixes for anything confirmed as a real issue. For anything flagged that is judged not to be a real issue, write down the specific disagreement and reasoning rather than silently dismissing it — this goes in the final report.

- [ ] **Step 4: Final report**

Summarize per the user's requested format: branch name, what existed vs. was created, exact migration filenames, what (if anything) was removed and why, full suite pass/fail count, code-review findings (fixed vs. left open), and any risk/ambiguity flagged along the way.
