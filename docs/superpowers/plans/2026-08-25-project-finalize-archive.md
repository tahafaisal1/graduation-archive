# Project Finalize + Archive Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the flagged gap in the Proposal/Project split — give `department_manager`/`department_staff` a way to upload the final project PDF, confirm examiners+score are recorded, and permanently archive an in-progress (`قيد التنفيذ`) `Project` into `مؤرشف`, at which point it becomes visible on public browse with a downloadable final file and is locked from further changes.

**Architecture:** A new nullable `final_file_path` column on `projects`. A new `Project::canBeFinalizedBy(User $user)` + `Project::finalizationBlockers()` pair (modeled on `Proposal::canBeModifiedBy()`/`canBeInstantiatedBy()`) drive both the backend gate and the readiness messages the frontend shows. A new `FinalizeProjectRequest` + `ProjectController::finalize()` + `POST /projects/{id}/finalize` route perform the one-shot action: store the PDF, flip `status_id` to `Project::STATUS_ARCHIVED`. `Projects/Show.vue` grows a finalize card next to the existing examiners/score card, reusing `ConfirmDelete.vue` (extended with an `orange` color) for the irreversible confirm step. `Public/Show.vue` switches its file link from the proposal's draft PDF to the project's `final_file_path`.

**Tech Stack:** Laravel 11, PHP 8.2, MariaDB (SQLite in-memory for tests), Pest PHP, Vue 3 + Inertia.js 2 + TypeScript, Tailwind CSS.

**Spec:** User's task message (2026-08-25, "THE GAP" + Steps 1-6) and the Step 1 audit findings recorded below and in this session's report to the user.

## Global Constraints

- Branch `worktree-project-finalize-archive`, worktree `.claude/worktrees/project-finalize-archive` (already created, reset onto local `main` tip `28e252b` — the worktree tool's default `origin/main` base was 39 commits stale and missing the entire Proposal/Project split; this was caught and corrected before any other work started). Do not merge to `main`.
- Field name, migration intent, and route are exactly as specified by the user: `final_file_path` (nullable string) on `projects`; `POST /projects/{id}/finalize` named `projects.finalize`.
- Finalize authorization mirrors `Proposal`'s convention, not the current (unscoped) examiner/score gate: `super_admin` always; otherwise the project must not already be `STATUS_ARCHIVED`, and the acting user must be `dept_manager` or `dept_staff` **of the proposal's department** (`project->proposal->department_id`), matching `StoreProposalRequest`'s dept_staff check (any dept_staff of that department, not restricted to the original creator — `Project` has no `created_by` of its own to check against).
- Readiness gate for finalize (confirmed 2026-08-26, supersedes the original "at least one" draft): **exactly two** examiners assigned — matching CLAUDE.md's documented fixed-2 business rule (defense committee = 2 examiners) — AND `final_score` not null AND a PDF file present on the request. Evaluation *notes* are not part of the gate (no test for it was listed).
- **Exactly-2 cap is already enforced at assign-time**, confirmed by reading `ProjectExaminerController::assign()` (lines 16-18): `if ($project->examiners()->count() >= 2) { return back()->with('error', ...); }` blocks a 3rd examiner today. So `finalizationBlockers()`'s ">2 examiners" branch is defensive only (unreachable via normal assign flow) — no separate hardening task needed, per the user's 2026-08-26 confirmation.
- File storage: `Storage::disk('public')->store('projects/final', 'public')` — a distinct subfolder from proposal drafts (`projects/`) so the two file kinds don't collide on disk. PDF only, max 15360 KB (15MB), matching the proposal upload convention.
- `ConfirmDelete.vue`'s `confirmColor` prop is extended from `'red' | 'green'` to also accept `'orange'` — there is no separate `ConfirmDialog` component in this codebase; every confirm-style dialog (delete, instantiate) already reuses `ConfirmDelete.vue` with a different color/label, and finalize follows the same convention.
- **Open Question A — RESOLVED 2026-08-26:** confirmed. "Do not touch that logic" meant business logic (assignment/evaluation rules), not "leave the endpoints exploitable after finalization." Task 5 adds a guard clause at the top of `assign()`/`remove()`/`store()`/`updateScore()` that rejects (with a flash error) once `status_id === STATUS_ARCHIVED`, leaving 100% of existing logic for non-finalized projects untouched. Fail-closed at the API layer, not just hidden in the UI — matches the prior branch's established pattern.
- **Open Question B — RESOLVED 2026-08-26:** exactly 2 examiners, not "at least one." CLAUDE.md's documented rule is correct (defense committee = 2 examiners); the original "at least one" draft was a loose simplification. `finalizationBlockers()` (Task 2) and the finalize gate (Task 3/4) now require `examiners()->count() === 2`. Separately confirmed: the exactly-2 ceiling is *already* enforced at assign-time by `ProjectExaminerController::assign()` (existing guard, lines 16-18, blocks a 3rd examiner) — so no additional hardening task is needed for the upper bound; this plan only adds the lower-bound check (blocking finalize below 2) in the readiness gate.
- Confirmed via audit, not to be re-derived: `projects` table has zero file columns today (`database/migrations/2026_08_24_160500_create_new_projects_table.php`); `project_lifecycle_status` seeds exactly `{1: قيد التنفيذ, 2: مؤرشف}`; no archive/finalize endpoint exists anywhere (`ProjectController` is GET-only `index`/`show`); `Projects/Show.vue` renders examiners at lines ~118-146 and score at ~159-171 with no file upload UI anywhere on the page; `AssignExaminerRequest`/`UpdateScoreRequest` both hard-code `authorize(): true` — the *only* gate on examiner/score changes today is the route's `role:super_admin,dept_manager` middleware, with **no department scoping at all** (a pre-existing gap, left untouched); `PublicController::browse()`/`show()` already filter on `Project::STATUS_ARCHIVED` and currently link to `project.proposal.draft_file_path` (the proposal's draft, not a project-specific final file — confirmed via `resources/js/pages/Public/Show.vue:158`).

---

## File Structure

**New:**
- `database/migrations/2026_08_25_150000_add_final_file_path_to_projects_table.php`
- `app/Http/Requests/FinalizeProjectRequest.php`
- `tests/Feature/Project/FinalizeProjectTest.php`
- `tests/Feature/Project/FinalizeProjectLockTest.php`

**Modified:**
- `app/Models/Project.php` — add `final_file_path` to `$fillable`; add `canBeFinalizedBy()`, `finalizationBlockers()`
- `app/Http/Controllers/ProjectController.php` — add `finalize()`
- `app/Http/Controllers/ProjectExaminerController.php` — add archived-guard to `assign()`/`remove()`
- `app/Http/Controllers/EvaluationController.php` — add archived-guard to `store()`/`updateScore()`
- `routes/web.php` — add `POST /projects/{id}/finalize`
- `resources/js/components/ConfirmDelete.vue` — add `'orange'` to `confirmColor`
- `resources/js/pages/Projects/Show.vue` — finalize card + confirm dialog + post-finalize file display
- `resources/js/pages/Public/Show.vue` — swap file link source
- `tests/Feature/Models/ProjectModelTest.php` — append `canBeFinalizedBy`/`finalizationBlockers` tests
- `CLAUDE.md`, `PROGRESS.md` — document the new field/endpoint/flow, remove the now-closed "flagged limitation" notes

---

## Task 1: Migration — `final_file_path` on `projects`

**Files:**
- Create: `database/migrations/2026_08_25_150000_add_final_file_path_to_projects_table.php`

**Interfaces:**
- Produces: `projects.final_file_path` (nullable string, no default) — consumed by Task 2 (`$fillable`), Task 4 (`finalize()`), Task 6 (Vue), Task 7 (Public/Show.vue).

- [ ] **Step 1: Write the migration**

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
            $table->string('final_file_path')->nullable()->after('final_score');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('final_file_path');
        });
    }
};
```

- [ ] **Step 2: Run the migration against the local dev DB and verify the column exists**

Run: `php artisan migrate`
Expected: `2026_08_25_150000_add_final_file_path_to_projects_table` listed as run; `DESCRIBE projects;` (or `php artisan tinker` → `Schema::getColumnListing('projects')`) shows `final_file_path`.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_08_25_150000_add_final_file_path_to_projects_table.php
git commit -m "feat: add final_file_path column to projects table"
```

---

## Task 2: `Project::canBeFinalizedBy()` + `finalizationBlockers()`

**Files:**
- Modify: `app/Models/Project.php`
- Test: `tests/Feature/Models/ProjectModelTest.php`

**Interfaces:**
- Consumes: `Project::STATUS_ARCHIVED`/`STATUS_IN_PROGRESS` (existing constants), `$this->proposal->department_id`, `$this->examiners()`, `$this->final_score`.
- Produces: `Project::canBeFinalizedBy(User $user): bool`, `Project::finalizationBlockers(): array` (list of Arabic strings) — consumed by Task 3 (`FinalizeProjectRequest`) and Task 6 (Vue, via a new prop the controller passes).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Models/ProjectModelTest.php`:

```php
function makeFinalizeProject(array $overrides = []): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    $proposal = Proposal::factory()->create(array_merge([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
    ], $overrides));

    $project = $proposal->instantiateProject($supervisor);

    return compact('dept', 'spec', 'project');
}

test('canBeFinalizedBy: dept_manager of the project department can finalize while قيد التنفيذ', function () {
    $data    = makeFinalizeProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);

    expect($data['project']->canBeFinalizedBy($manager))->toBeTrue();
});

test('canBeFinalizedBy: dept_staff of the project department can finalize', function () {
    $data  = makeFinalizeProject();
    $staff = userWithRole('dept_staff', ['department_id' => $data['dept']->id]);

    expect($data['project']->canBeFinalizedBy($staff))->toBeTrue();
});

test('canBeFinalizedBy: dept_manager of a different department cannot finalize', function () {
    $data         = makeFinalizeProject();
    $otherDept    = Department::factory()->create();
    $otherManager = userWithRole('dept_manager', ['department_id' => $otherDept->id]);

    expect($data['project']->canBeFinalizedBy($otherManager))->toBeFalse();
});

test('canBeFinalizedBy: super_admin can always finalize', function () {
    $data  = makeFinalizeProject();
    $admin = userWithRole('super_admin');

    expect($data['project']->canBeFinalizedBy($admin))->toBeTrue();
});

test('canBeFinalizedBy: false once already مؤرشف', function () {
    $data    = makeFinalizeProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    $data['project']->update(['status_id' => Project::STATUS_ARCHIVED]);

    expect($data['project']->canBeFinalizedBy($manager))->toBeFalse();
});

test('finalizationBlockers: lists missing examiners and missing score when zero examiners assigned', function () {
    $data = makeFinalizeProject();

    expect($data['project']->finalizationBlockers())
        ->toBe(['بانتظار تعيين ممتحنين', 'بانتظار الدرجة']);
});

test('finalizationBlockers: lists "one more examiner needed" when exactly one examiner assigned', function () {
    $data = makeFinalizeProject();
    $examiner = \App\Models\Examiner::factory()->create(['department_id' => $data['dept']->id]);
    $data['project']->examiners()->attach($examiner->id, ['assigned_by' => null]);
    $data['project']->update(['final_score' => 88]);

    expect($data['project']->finalizationBlockers())->toBe(['بانتظار تعيين ممتحن آخر']);
});

test('finalizationBlockers: empty once exactly two examiners assigned and score set', function () {
    $data = makeFinalizeProject();
    $examinerA = \App\Models\Examiner::factory()->create(['department_id' => $data['dept']->id]);
    $examinerB = \App\Models\Examiner::factory()->create(['department_id' => $data['dept']->id]);
    $data['project']->examiners()->attach($examinerA->id, ['assigned_by' => null]);
    $data['project']->examiners()->attach($examinerB->id, ['assigned_by' => null]);
    $data['project']->update(['final_score' => 88]);

    expect($data['project']->finalizationBlockers())->toBe([]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor\bin\pest tests/Feature/Models/ProjectModelTest.php -v`
Expected: FAIL — `canBeFinalizedBy`/`finalizationBlockers` do not exist on `Project`.

- [ ] **Step 3: Implement on `Project`**

In `app/Models/Project.php`, add `'final_file_path'` to `$fillable`, and add these two methods (after `evaluations()`):

```php
public function canBeFinalizedBy(User $user): bool
{
    if ($user->hasRole('super_admin')) {
        return true;
    }

    if ($this->status_id === self::STATUS_ARCHIVED) {
        return false;
    }

    $departmentId = $this->proposal->department_id;

    if ($user->hasRole('dept_manager') && $user->department_id === $departmentId) {
        return true;
    }

    return $user->hasRole('dept_staff') && $user->department_id === $departmentId;
}

/**
 * @return array<int, string>
 */
public function finalizationBlockers(): array
{
    $blockers = [];

    $examinerCount = $this->examiners()->count();

    if ($examinerCount === 0) {
        $blockers[] = 'بانتظار تعيين ممتحنين';
    } elseif ($examinerCount === 1) {
        $blockers[] = 'بانتظار تعيين ممتحن آخر';
    } elseif ($examinerCount > 2) {
        // Defensive only — ProjectExaminerController::assign() already blocks a 3rd examiner.
        $blockers[] = 'يجب أن يكون عدد الممتحنين اثنين بالضبط';
    }

    if ($this->final_score === null) {
        $blockers[] = 'بانتظار الدرجة';
    }

    return $blockers;
}
```

Add `use App\Models\User;` import if not already present (check first — `Project.php` currently has no `User` import since `instantiatedBy()` references `User::class` by FQCN already via `use Illuminate\Database\Eloquent\Relations\BelongsTo` + inline `User::class` — verify and add `use App\Models\User;` only if the type-hint `User $user` needs it unqualified).

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor\bin\pest tests/Feature/Models/ProjectModelTest.php -v`
Expected: PASS, all 9 tests (1 existing + 5 canBeFinalizedBy + 3 finalizationBlockers new).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Project.php tests/Feature/Models/ProjectModelTest.php
git commit -m "feat: add Project::canBeFinalizedBy and finalizationBlockers"
```

---

## Task 3: `FinalizeProjectRequest`

**Files:**
- Create: `app/Http/Requests/FinalizeProjectRequest.php`

**Interfaces:**
- Consumes: `Project::canBeFinalizedBy()`, `Project::finalizationBlockers()` (Task 2).
- Produces: validated `final_file` (`UploadedFile`) — consumed by Task 4 (`ProjectController::finalize()`).

- [ ] **Step 1: Write the request**

```php
<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class FinalizeProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::where('is_deleted', false)->find($this->route('id'));

        if (! $project) {
            return true;
        }

        return $project->canBeFinalizedBy($this->user());
    }

    public function rules(): array
    {
        return [
            'final_file' => ['required', 'file', 'mimes:pdf', 'max:15360'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $project = Project::where('is_deleted', false)->find($this->route('id'));

            if (! $project) {
                return;
            }

            foreach ($project->finalizationBlockers() as $blocker) {
                $validator->errors()->add('final_file', $blocker);
            }
        });
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Requests/FinalizeProjectRequest.php
git commit -m "feat: add FinalizeProjectRequest"
```

(No standalone test for the Form Request — its `authorize()`/readiness logic is exercised end-to-end by Task 4's feature tests, matching this codebase's existing convention of testing Form Requests only through their controller route, e.g. `AssignExaminerRequest`/`UpdateScoreRequest` have no dedicated request test files.)

---

## Task 4: `ProjectController::finalize()` + route

**Files:**
- Modify: `app/Http/Controllers/ProjectController.php`
- Modify: `routes/web.php`
- Test: Create `tests/Feature/Project/FinalizeProjectTest.php`

**Interfaces:**
- Consumes: `FinalizeProjectRequest` (Task 3), `Project::canBeFinalizedBy()`/`finalizationBlockers()` (Task 2).
- Produces: `POST /projects/{id}/finalize` named `projects.finalize` — consumed by Task 6 (Vue form submission).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Project/FinalizeProjectTest.php`:

```php
<?php

use App\Models\Department;
use App\Models\Examiner;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use Database\Seeders\ProjectLifecycleStatusSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
    $this->seed(ProjectLifecycleStatusSeeder::class);
    Storage::fake('public');
});

function makeFinalizableProject(): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    $proposal = Proposal::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
    ]);

    $project   = $proposal->instantiateProject($supervisor);
    $examinerA = Examiner::factory()->create(['department_id' => $dept->id]);
    $examinerB = Examiner::factory()->create(['department_id' => $dept->id]);

    return compact('dept', 'project', 'examinerA', 'examinerB');
}

function attachBothExaminers(array $data, int $assignedBy): void
{
    $data['project']->examiners()->attach($data['examinerA']->id, ['assigned_by' => $assignedBy]);
    $data['project']->examiners()->attach($data['examinerB']->id, ['assigned_by' => $assignedBy]);
}

test('cannot finalize with zero examiners assigned', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('final_file');

    $data['project']->refresh();
    expect($data['project']->status_id)->toBe(Project::STATUS_IN_PROGRESS)
        ->and($data['project']->final_file_path)->toBeNull();
});

test('cannot finalize with only one examiner assigned', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    $data['project']->examiners()->attach($data['examinerA']->id, ['assigned_by' => $manager->id]);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('final_file');

    $data['project']->refresh();
    expect($data['project']->status_id)->toBe(Project::STATUS_IN_PROGRESS);
});

test('cannot finalize without a score set', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, $manager->id);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('final_file');

    $data['project']->refresh();
    expect($data['project']->status_id)->toBe(Project::STATUS_IN_PROGRESS);
});

test('cannot finalize without a file uploaded', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, $manager->id);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [])
        ->assertSessionHasErrors('final_file');

    $data['project']->refresh();
    expect($data['project']->status_id)->toBe(Project::STATUS_IN_PROGRESS);
});

test('finalizing with exactly two examiners, score, and file succeeds and locks the project', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, $manager->id);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('projects.show', $data['project']->id));

    $data['project']->refresh();
    expect($data['project']->status_id)->toBe(Project::STATUS_ARCHIVED)
        ->and($data['project']->final_file_path)->not->toBeNull();

    Storage::disk('public')->assertExists($data['project']->final_file_path);
});

test('dept_manager of a different department cannot finalize', function () {
    $data         = makeFinalizableProject();
    $otherDept    = Department::factory()->create();
    $otherManager = userWithRole('dept_manager', ['department_id' => $otherDept->id]);
    attachBothExaminers($data, $otherManager->id);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($otherManager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('dept_staff of the same department can finalize', function () {
    $data  = makeFinalizableProject();
    $staff = userWithRole('dept_staff', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, $staff->id);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($staff)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('projects.show', $data['project']->id));
});

test('supervisor cannot finalize', function () {
    $data       = makeFinalizableProject();
    $supervisor = userWithRole('supervisor', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, null);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($supervisor)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});

test('super_admin can finalize any project regardless of department', function () {
    $data  = makeFinalizableProject();
    $admin = userWithRole('super_admin');
    attachBothExaminers($data, $admin->id);
    $data['project']->update(['final_score' => 90]);

    $this->actingAs($admin)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertRedirect(route('projects.show', $data['project']->id));
});

test('cannot finalize an already-finalized project', function () {
    $data    = makeFinalizableProject();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    attachBothExaminers($data, $manager->id);
    $data['project']->update(['final_score' => 90, 'status_id' => Project::STATUS_ARCHIVED]);

    $this->actingAs($manager)
        ->post(route('projects.finalize', $data['project']->id), [
            'final_file' => UploadedFile::fake()->create('final.pdf', 100, 'application/pdf'),
        ])
        ->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor\bin\pest tests/Feature/Project/FinalizeProjectTest.php -v`
Expected: FAIL — route `projects.finalize` does not exist (`RouteNotFoundException`).

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the existing `Route::middleware(['auth'])->group(...)` block (the one already containing `proposals.*`/`projects.index`/`projects.show`), add:

```php
    Route::post('projects/{id}/finalize', [ProjectController::class, 'finalize'])
        ->name('projects.finalize');
```

- [ ] **Step 4: Implement `finalize()` on `ProjectController`**

Add to `app/Http/Controllers/ProjectController.php` (add `use App\Http\Requests\FinalizeProjectRequest;` and `use Illuminate\Http\RedirectResponse;` imports):

```php
public function finalize(FinalizeProjectRequest $request, int $id): RedirectResponse
{
    $project = Project::where('is_deleted', false)->findOrFail($id);

    $path = $request->file('final_file')->store('projects/final', 'public');

    $project->update([
        'final_file_path' => $path,
        'status_id'       => self::class === Project::class ? null : null, // placeholder removed below
    ]);

    return redirect()->route('projects.show', $project)->with('success', 'تم أرشفة المشروع بنجاح');
}
```

Replace the placeholder `status_id` line — this was left in by mistake during planning; the actual update call is:

```php
public function finalize(FinalizeProjectRequest $request, int $id): RedirectResponse
{
    $project = Project::where('is_deleted', false)->findOrFail($id);

    $path = $request->file('final_file')->store('projects/final', 'public');

    $project->update([
        'final_file_path' => $path,
        'status_id'       => Project::STATUS_ARCHIVED,
    ]);

    return redirect()->route('projects.show', $project)->with('success', 'تم أرشفة المشروع بنجاح');
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor\bin\pest tests/Feature/Project/FinalizeProjectTest.php -v`
Expected: PASS, all 10 tests.

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 232 (baseline) + 8 (Task 2) + 10 (Task 4) = 250 passed, 0 failed.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ProjectController.php routes/web.php tests/Feature/Project/FinalizeProjectTest.php
git commit -m "feat: add POST /projects/{id}/finalize endpoint"
```

---

## Task 5: Lock examiner/score endpoints once finalized (Open Question A — confirm before running)

**Files:**
- Modify: `app/Http/Controllers/ProjectExaminerController.php`
- Modify: `app/Http/Controllers/EvaluationController.php`
- Test: Create `tests/Feature/Project/FinalizeProjectLockTest.php`

**Interfaces:**
- Consumes: `Project::STATUS_ARCHIVED` (existing constant).
- Produces: nothing new — this task only adds a guard clause; no signature changes.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Project/FinalizeProjectLockTest.php`:

```php
<?php

use App\Models\Department;
use App\Models\Examiner;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
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

function makeArchivedProjectWithExaminer(): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    $proposal = Proposal::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $spec->id,
        'supervisor_id'     => $supervisor->id,
        'status_id'         => Proposal::STATUS_ARCHIVED,
    ]);

    $project  = $proposal->instantiateProject($supervisor);
    $examiner = Examiner::factory()->create(['department_id' => $dept->id]);
    $project->examiners()->attach($examiner->id, ['assigned_by' => null]);
    $project->update(['final_score' => 90, 'status_id' => Project::STATUS_ARCHIVED]);

    return compact('dept', 'project', 'examiner');
}

test('cannot assign examiner to a finalized project', function () {
    $data      = makeArchivedProjectWithExaminer();
    $manager   = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);
    $examiner2 = Examiner::factory()->create(['department_id' => $data['dept']->id]);

    $this->actingAs($manager)
        ->post(route('projects.assign-examiner', $data['project']->id), ['examiner_id' => $examiner2->id])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('project_examiners', [
        'project_id' => $data['project']->id, 'examiner_id' => $examiner2->id,
    ]);
});

test('cannot remove examiner from a finalized project', function () {
    $data    = makeArchivedProjectWithExaminer();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);

    $this->actingAs($manager)
        ->delete(route('projects.remove-examiner', [$data['project']->id, $data['examiner']->id]))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('project_examiners', [
        'project_id' => $data['project']->id, 'examiner_id' => $data['examiner']->id,
    ]);
});

test('cannot add evaluation notes to a finalized project', function () {
    $data    = makeArchivedProjectWithExaminer();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);

    $this->actingAs($manager)
        ->post(route('projects.evaluation', $data['project']->id), [
            'examiner_id' => $data['examiner']->id,
            'notes'       => 'محاولة تعديل بعد الأرشفة',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('evaluations', ['project_id' => $data['project']->id]);
});

test('cannot change score on a finalized project', function () {
    $data    = makeArchivedProjectWithExaminer();
    $manager = userWithRole('dept_manager', ['department_id' => $data['dept']->id]);

    $this->actingAs($manager)
        ->patch(route('projects.score', $data['project']->id), ['final_score' => 50])
        ->assertRedirect()
        ->assertSessionHas('error');

    $data['project']->refresh();
    expect((float) $data['project']->final_score)->toBe(90.0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor\bin\pest tests/Feature/Project/FinalizeProjectLockTest.php -v`
Expected: FAIL — all 4 currently succeed (no guard exists yet), so these assertions on rejection fail.

- [ ] **Step 3: Add the guard to `ProjectExaminerController`**

In `app/Http/Controllers/ProjectExaminerController.php`, at the top of both `assign()` and `remove()`, immediately after `$project = Project::where('is_deleted', false)->findOrFail($projectId);`:

```php
        if ($project->status_id === Project::STATUS_ARCHIVED) {
            return back()->with('error', 'لا يمكن التعديل على مشروع مؤرشف نهائيًا');
        }
```

- [ ] **Step 4: Add the guard to `EvaluationController`**

In `app/Http/Controllers/EvaluationController.php`, at the top of both `store()` and `updateScore()`, immediately after `$project = Project::where('is_deleted', false)->findOrFail($projectId);`:

```php
        if ($project->status_id === Project::STATUS_ARCHIVED) {
            return back()->with('error', 'لا يمكن التعديل على مشروع مؤرشف نهائيًا');
        }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor\bin\pest tests/Feature/Project/FinalizeProjectLockTest.php -v`
Expected: PASS, all 4 tests.

- [ ] **Step 6: Run the full suite to confirm no regressions**

Run: `php artisan test`
Expected: 250 (prior total) + 4 = 254 passed, 0 failed. In particular, all pre-existing `ExaminerTest.php` tests still pass unchanged, since they all operate on `STATUS_IN_PROGRESS` projects.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ProjectExaminerController.php app/Http/Controllers/EvaluationController.php tests/Feature/Project/FinalizeProjectLockTest.php
git commit -m "feat: lock examiner/evaluation/score changes once a project is finalized"
```

---

## Task 6: Frontend — finalize card on `Projects/Show.vue`

**Files:**
- Modify: `resources/js/components/ConfirmDelete.vue`
- Modify: `resources/js/pages/Projects/Show.vue`
- Modify: `app/Http/Controllers/ProjectController.php` (pass blockers/canFinalize as props)

**Interfaces:**
- Consumes: `route('projects.finalize', id)` (Task 4), `project.final_file_path` (Task 1).
- Produces: nothing consumed by later tasks except the visual/manual verification in Task 8.

- [ ] **Step 1: Extend `ConfirmDelete.vue`'s color prop**

In `resources/js/components/ConfirmDelete.vue`, change:

```ts
confirmColor?: 'red' | 'green';
```

to:

```ts
confirmColor?: 'red' | 'green' | 'orange';
```

And change the button class binding from:

```html
:class="[
    'rounded-lg px-4 py-2 text-sm font-medium text-white',
    confirmColor === 'green' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700',
]"
```

to:

```html
:class="[
    'rounded-lg px-4 py-2 text-sm font-medium text-white',
    confirmColor === 'green' ? 'bg-green-600 hover:bg-green-700'
        : confirmColor === 'orange' ? 'bg-orange-600 hover:bg-orange-700'
        : 'bg-red-600 hover:bg-red-700',
]"
```

- [ ] **Step 2: Pass finalize data from the controller**

In `app/Http/Controllers/ProjectController.php::show()`, after building `$availableExaminers`, add:

```php
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return Inertia::render('Projects/Show', [
            'project'            => $project,
            'availableExaminers' => $availableExaminers,
            'canFinalize'        => $project->canBeFinalizedBy($user),
            'finalizationBlockers' => $project->finalizationBlockers(),
        ]);
```

(Replace the existing `return Inertia::render(...)` at the end of `show()` with this version — the two new keys are additive.)

- [ ] **Step 3: Add the finalize UI to `Projects/Show.vue`**

Add to the `<script setup>` block (after the existing `const scoreIsPass = ...` line):

```ts
const props2 = defineProps<{ canFinalize: boolean; finalizationBlockers: string[] }>();
```

This is wrong — Vue only allows one `defineProps` call per component. Instead, merge into the existing `defineProps` call. Change:

```ts
const props = defineProps<{ project: Project; availableExaminers: Examiner[] }>();
```

to:

```ts
const props = defineProps<{
    project: Project;
    availableExaminers: Examiner[];
    canFinalize: boolean;
    finalizationBlockers: string[];
}>();
```

Then add, after the `removeExaminer`/`evaluationFor` functions:

```ts
const showFinalizeConfirm = ref(false);
const finalizeForm = useForm({ final_file: null as File | null });
const isFinalized = computed(() => props.project.status?.status_name === 'مؤرشف');

function onFinalFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    finalizeForm.final_file = input.files?.[0] ?? null;
}

function finalizeProject() {
    finalizeForm.post(route('projects.finalize', props.project.id), {
        forceFormData: true,
        onFinish: () => (showFinalizeConfirm.value = false),
    });
}
```

Add `useForm` to the existing `@inertiajs/vue3` import line (change `import { Head, router, usePage } from '@inertiajs/vue3';` to `import { Head, router, useForm, usePage } from '@inertiajs/vue3';`).

Add this card to the template, in the right-column `<div class="space-y-5">` block, right after the "الدرجة النهائية" card's closing `</div>` and before the outer column's closing `</div>`:

```html
                    <div v-if="!isFinalized" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">رفع الملف النهائي والأرشفة</h2>
                        <template v-if="canFinalize">
                            <ul v-if="finalizationBlockers.length > 0" class="mb-3 space-y-1 text-sm text-amber-600 dark:text-amber-400">
                                <li v-for="blocker in finalizationBlockers" :key="blocker">{{ blocker }}</li>
                            </ul>
                            <p v-else class="mb-3 text-sm text-green-600 dark:text-green-400">جاهز للأرشفة</p>
                            <input type="file" accept="application/pdf" class="mb-3 block w-full text-sm text-gray-600 dark:text-gray-400" @change="onFinalFileChange" />
                            <button
                                type="button"
                                :disabled="finalizationBlockers.length > 0 || !finalizeForm.final_file"
                                class="w-full rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="showFinalizeConfirm = true"
                            >
                                أرشفة نهائية
                            </button>
                        </template>
                        <p v-else class="text-sm text-gray-500">لا تملك صلاحية أرشفة هذا المشروع</p>
                    </div>
                    <div v-else class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">الملف النهائي</h2>
                        <a :href="'/storage/' + project.final_file_path" target="_blank" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                            تحميل الملف النهائي
                        </a>
                    </div>
```

Add `final_file_path: string | null;` and `status: ProjectStatus | null;` (already present) to the `Project` interface — change:

```ts
interface Project {
    id: number;
    final_score: string | null;
    visit_count: number;
```

to:

```ts
interface Project {
    id: number;
    final_score: string | null;
    final_file_path: string | null;
    visit_count: number;
```

Add the confirm dialog near the existing `<ConfirmDelete>`/`<AssignExaminerModal>` at the bottom of the template:

```html
        <ConfirmDelete
            :show="showFinalizeConfirm"
            title="تأكيد الأرشفة النهائية"
            message="سيتم أرشفة هذا المشروع نهائيًا ولن يمكن التراجع عن هذه العملية أو تعديل الممتحنين/الدرجة بعدها."
            confirm-label="أرشفة نهائية"
            confirm-color="orange"
            @confirmed="finalizeProject"
            @cancelled="showFinalizeConfirm = false"
        />
```

- [ ] **Step 4: Manual smoke check (frontend build)**

Run: `npm run build`
Expected: clean build, no TypeScript errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/ConfirmDelete.vue resources/js/pages/Projects/Show.vue app/Http/Controllers/ProjectController.php
git commit -m "feat: add finalize/archive UI to Projects/Show.vue"
```

---

## Task 7: Public browse/show — point file link at `final_file_path`

**Files:**
- Modify: `resources/js/pages/Public/Show.vue`

**Interfaces:**
- Consumes: `project.final_file_path` (Task 1) — already present on every `Project` returned by `PublicController::show()`/`browse()` since it's a real column, no backend eager-load change needed.

- [ ] **Step 1: Update the TypeScript interface**

In `resources/js/pages/Public/Show.vue`, change:

```ts
interface Project {
    id: number;
    final_score: string | null;
    visit_count: number;
```

to:

```ts
interface Project {
    id: number;
    final_score: string | null;
    final_file_path: string | null;
    visit_count: number;
```

- [ ] **Step 2: Update the template**

Change:

```html
            <div v-if="project.proposal.draft_file_path" class="bg-surface border border-border rounded-xl p-6 mb-6">
```

and its `:href` binding:

```html
                    :href="'/storage/' + project.proposal.draft_file_path"
```

to:

```html
            <div v-if="project.final_file_path" class="bg-surface border border-border rounded-xl p-6 mb-6">
```

```html
                    :href="'/storage/' + project.final_file_path"
```

- [ ] **Step 3: Build and verify**

Run: `npm run build`
Expected: clean build.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Public/Show.vue
git commit -m "fix: public project page links to the project's final file, not the proposal draft"
```

---

## Task 8: Full verification (tests + build)

- [ ] **Step 1: Run the full Pest suite**

Run: `php artisan test`
Expected: 254 passed (232 baseline + 8 Task 2 + 10 Task 4 + 4 Task 5), 0 failed.

- [ ] **Step 2: Run the frontend build**

Run: `npm run build`
Expected: clean, no errors.

- [ ] **Step 3: Record results for the final report** (no commit — this is a verification checkpoint)

---

## Task 9: Manual Playwright verification

- [ ] **Step 1: Start a dev server on a free port** (not conflicting with any other running instance), e.g. `php artisan serve --port=8001` and `npm run dev` (or serve the built assets).

- [ ] **Step 2: Full path — log in as a dept_manager, open an in-progress (قيد التنفيذ) project**

Verify: examiners/score card unchanged; new "رفع الملف النهائي والأرشفة" card shows "بانتظار تعيين ممتحنين" and "بانتظار الدرجة", finalize button disabled.

- [ ] **Step 3: Assign one examiner, verify blocker updates to "بانتظار تعيين ممتحن آخر", then assign a second examiner and set a score**

Verify: after the first examiner, blocker text changes from "بانتظار تعيين ممتحنين" to "بانتظار تعيين ممتحن آخر"; after the second examiner + score, shows "جاهز للأرشفة".

- [ ] **Step 4: Choose a PDF file, click "أرشفة نهائية"**

Verify: `ConfirmDelete` dialog appears in orange with the irreversibility message; clicking confirm submits.

- [ ] **Step 5: After redirect, verify the project is locked**

Verify: status badge now shows "مؤرشف"; examiners/score card no longer shows manage controls (or attempting to change them via direct route hits the flash error); a "الملف النهائي" card shows a working download link.

- [ ] **Step 6: Verify public visibility**

Log out, visit `/browse`, confirm the project now appears (previously invisible per the flagged limitation); open its public show page, confirm the download link points at the final file and works.

- [ ] **Step 7: Record pass/fail per step for the final report**

---

## Task 10: Documentation

- [ ] **Step 1: Update `CLAUDE.md`**

Add a new bullet at the top of "Current Status" describing: `final_file_path` column, `Project::canBeFinalizedBy()`/`finalizationBlockers()`, `POST /projects/{id}/finalize`, the examiner/evaluation lock-on-archive guard, and the frontend finalize card. Remove/supersede the now-stale "Flagged limitation: no UI currently exists to move a project from قيد التنفيذ to مؤرشف" bullets (there are two occurrences — one in "Key Business Rules", one in the Proposal/Project Split entry's own bullet list).

- [ ] **Step 2: Update `PROGRESS.md`**

Mirror the same information in the "Major Changes" section (Section 1) and the `projects` table schema (Section 2) — add the `final_file_path` column row, and add a new "Controllers" entry (Section 4) documenting `ProjectController::finalize()` alongside the existing `index()`/`show()` rows.

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md PROGRESS.md
git commit -m "docs: document project finalize/archive flow"
```

---

## Self-Review Notes (completed during planning, not a task)

- **Spec coverage:** Steps 3-6 of the user's message map to Tasks 1-10 above: field+migration (Task 1), finalize action with validation (Tasks 2-4), lock-on-archive (Task 5), Vue UI (Task 6), post-finalize file display (Task 6 Step 3), public browse link (Task 7), TDD test list (Tasks 2/4/5 — all 6 listed test scenarios plus role/department edge cases), manual verification (Task 9), docs (Task 10).
- **Placeholder scan:** Task 4 Step 4 intentionally shows a wrong-then-corrected snippet because the plan author caught their own mistake while drafting — the final code block in that step is the one to implement; flagging here so an executor doesn't implement the placeholder version.
- **Type consistency:** `canBeFinalizedBy(User $user): bool` and `finalizationBlockers(): array` (Task 2) are called identically in Task 3 (`FinalizeProjectRequest`) and Task 6 (`ProjectController::show()`). `final_file_path` is spelled identically across the migration (Task 1), model fillable (Task 2), controller (Task 4), and both Vue files (Tasks 6-7).
