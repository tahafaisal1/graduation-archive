# Paper-Approval Proposal Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the unused digital supervisor/department approval-gate fields with a paper-approval model: approval happens outside the system, and the system instead enforces that a proposal (status مقترح) is replaceable and deletable only by its creator or their department manager, until a department manager archives it (status مؤرشف), after which it is locked (except for `super_admin`).

**Architecture:** Drop the two approval-gate FK pairs and add a single `created_by` FK on `projects`. Centralize the "who can touch this pending/archived project" predicate as two boolean methods on the `Project` model (`canBeModifiedBy()`, `canBeArchivedBy()`) — the codebase has no Policy class anywhere, so this keeps a single source of truth without introducing a new pattern. Reuse the existing `update()`/`destroy()` endpoints for "replace" and "delete" (they already do the right HTTP verb + body shape); rename `approve()` → `archive()` to match the new business language (approval is paper-based; archiving is the only digital transition left). Push authorization into `FormRequest::authorize()` for all four actions (create/replace/delete/archive), replacing the old inline `authorizeEdit()` private method.

**Tech Stack:** Laravel 11 (PHP 8.2), Pest, Vue 3 + Inertia 2 + TypeScript, Spatie laravel-permission v6.

**Spec:** This plan is written directly from the user's Step 1-8 instructions in-conversation (no separate spec file) plus the Step 1 audit findings recorded above in the session transcript.

## Global Constraints

- Status IDs are fixed and unchanged: `current_status_id = 1` → مؤرشف (archived), `= 2` → مقترح (proposal/pending). Do not renumber.
- Spatie roles are exactly: `super_admin`, `dept_manager`, `supervisor`, `dept_staff`, `viewer` (see `database/seeders/RoleSeeder.php`). Use these slugs, not "department_manager"/"department_staff".
- `super_admin` bypasses every new restriction (status lock, ownership, department scope) on create/replace/delete/archive.
- No `ProjectPolicy` (or any Policy class) exists anywhere in `app/` — do not introduce one; keep authorization in FormRequests + a small set of boolean methods on `Project`.
- `created_by` is nullable (existing archived/demo/seeded projects have no known creator — do not backfill).
- Full existing suite must stay green (220/220 today); any pre-existing test whose *expected behavior* the new business rules intentionally change (see Task 4/5) must be updated, not worked around.

---

## File Structure

- Create: `database/migrations/2026_08_23_120000_drop_approval_gate_columns_from_projects_table.php` — drops the 4 approval-gate columns + their FKs.
- Create: `database/migrations/2026_08_23_120100_add_created_by_to_projects_table.php` — adds nullable `created_by` FK to `users`.
- Modify: `app/Models/Project.php` — remove approval-gate fillable/casts/relations/helpers; add `created_by` fillable, `STATUS_ARCHIVED`/`STATUS_PENDING` public constants, `createdBy()` relation, `canBeModifiedBy()`/`canBeArchivedBy()` methods.
- Delete: `tests/Feature/Project/ProjectApprovalTest.php` — entirely about the removed fields.
- Modify: `app/Http/Controllers/ProjectController.php` — set `created_by` in `store()`; remove `authorizeEdit()` (replaced by `Project::canBeModifiedBy()`); rename `approve()` → `archive()`; use the new FormRequests.
- Create: `app/Http/Requests/DeleteProjectRequest.php` — authorization for soft-delete.
- Create: `app/Http/Requests/ArchiveProjectRequest.php` — authorization for archiving.
- Modify: `app/Http/Requests/UpdateProjectRequest.php` — `authorize()` now delegates to `Project::canBeModifiedBy()`.
- Modify: `routes/web.php` — rename `projects.approve` → `projects.archive` (`POST projects/{id}/archive`), drop the now-redundant `role:dept_manager,super_admin` middleware (the FormRequest supersedes it).
- Modify: `resources/js/pages/Projects/Show.vue` — ownership/status-aware `canReplace`/`canDelete`/`canArchive`; rename approve → archive; pass `created_by` through.
- Modify: `resources/js/pages/Projects/Index.vue` — same, per-row.
- Modify: `tests/Feature/Roles/RoleVerificationTest.php` — fix two fixtures whose expected behavior the new rules intentionally change (see Task 4/5).
- Modify: `tests/Feature/Project/ProjectTest.php` — add the new permission-matrix tests.
- Modify: `CLAUDE.md`, `PROGRESS.md` — reflect paper-approval model + new permission matrix + removed fields (Task 8).

---

### Task 1: Remove the unused approval-gate fields

**Files:**
- Create: `database/migrations/2026_08_23_120000_drop_approval_gate_columns_from_projects_table.php`
- Modify: `app/Models/Project.php`
- Delete: `tests/Feature/Project/ProjectApprovalTest.php`

**Interfaces:**
- Produces: `Project` with no `supervisor_approved_by/_at`, `department_approved_by/_at`, no `supervisorApprovedBy()`/`departmentApprovedBy()`/`isSupervisorApproved()`/`isDepartmentApproved()`.

- [ ] **Step 1: Delete the test file that only tests the removed fields**

```bash
rm tests/Feature/Project/ProjectApprovalTest.php
```

- [ ] **Step 2: Write the migration**

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
            $table->dropConstrainedForeignId('supervisor_approved_by');
            $table->dropColumn('supervisor_approved_at');
            $table->dropConstrainedForeignId('department_approved_by');
            $table->dropColumn('department_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('supervisor_approved_by')->nullable()->after('current_status_id')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_approved_at')->nullable()->after('supervisor_approved_by');
            $table->foreignId('department_approved_by')->nullable()->after('supervisor_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('department_approved_at')->nullable()->after('department_approved_by');
        });
    }
};
```

Save as `database/migrations/2026_08_23_120000_drop_approval_gate_columns_from_projects_table.php`.

- [ ] **Step 3: Remove the fields from the Project model**

In `app/Models/Project.php`, remove from `$fillable`: `supervisor_approved_by`, `supervisor_approved_at`, `department_approved_by`, `department_approved_at`. Remove from `casts()`: `supervisor_approved_at`, `department_approved_at`. Delete the methods `supervisorApprovedBy()`, `departmentApprovedBy()`, `isSupervisorApproved()`, `isDepartmentApproved()` entirely.

- [ ] **Step 4: Run the full suite to confirm nothing referenced the removed fields**

Run: `php artisan test`
Expected: only failures are the ones from the deleted `ProjectApprovalTest.php` disappearing (i.e. total count drops by 2, everything else still passes). If anything else fails, that's a reference Step 1's audit missed — investigate before continuing (systematic-debugging).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_23_120000_drop_approval_gate_columns_from_projects_table.php app/Models/Project.php tests/Feature/Project/ProjectApprovalTest.php
git commit -m "feat: remove unused digital approval-gate fields (approval is paper-based)"
```

---

### Task 2: Add `created_by` + centralize status/permission logic on the model

**Files:**
- Create: `database/migrations/2026_08_23_120100_add_created_by_to_projects_table.php`
- Modify: `app/Models/Project.php`
- Test: `tests/Feature/Models/ProjectModelTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `Project::STATUS_ARCHIVED = 1`, `Project::STATUS_PENDING = 2` (public constants); `Project::createdBy(): BelongsTo`; `Project->canBeModifiedBy(User $user): bool`; `Project->canBeArchivedBy(User $user): bool`. These four are what Task 3/4/5 controllers and FormRequests call.

- [ ] **Step 1: Write the failing model test**

Add to `tests/Feature/Models/ProjectModelTest.php` (create the describe block if the file doesn't already have one for this — check the existing file first and append inside its existing structure):

```php
use App\Models\Department;
use App\Models\Project;
use App\Models\Specialization;
use App\Models\User;

test('canBeModifiedBy: super_admin can always modify regardless of status or ownership', function () {
    $dept = Department::factory()->create();
    $project = Project::factory()->create([
        'department_id' => $dept->id,
        'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
        'supervisor_id' => User::factory()->create()->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
        'created_by' => null,
    ]);
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    expect($project->canBeModifiedBy($admin))->toBeTrue();
});

test('canBeModifiedBy: creator can modify their own pending project in their department', function () {
    $dept = Department::factory()->create();
    $creator = User::factory()->create(['department_id' => $dept->id]);
    $creator->assignRole('dept_staff');
    $project = Project::factory()->create([
        'department_id' => $dept->id,
        'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
        'supervisor_id' => User::factory()->create()->id,
        'current_status_id' => Project::STATUS_PENDING,
        'created_by' => $creator->id,
    ]);

    expect($project->canBeModifiedBy($creator))->toBeTrue();
});

test('canBeModifiedBy: dept_manager of same department can modify a pending project even if not the creator', function () {
    $dept = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $dept->id]);
    $manager->assignRole('dept_manager');
    $project = Project::factory()->create([
        'department_id' => $dept->id,
        'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
        'supervisor_id' => User::factory()->create()->id,
        'current_status_id' => Project::STATUS_PENDING,
        'created_by' => User::factory()->create()->id,
    ]);

    expect($project->canBeModifiedBy($manager))->toBeTrue();
});

test('canBeModifiedBy: non-creator dept_staff in same department cannot modify', function () {
    $dept = Department::factory()->create();
    $otherStaff = User::factory()->create(['department_id' => $dept->id]);
    $otherStaff->assignRole('dept_staff');
    $project = Project::factory()->create([
        'department_id' => $dept->id,
        'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
        'supervisor_id' => User::factory()->create()->id,
        'current_status_id' => Project::STATUS_PENDING,
        'created_by' => User::factory()->create()->id,
    ]);

    expect($project->canBeModifiedBy($otherStaff))->toBeFalse();
});

test('canBeModifiedBy: creator cannot modify once archived', function () {
    $dept = Department::factory()->create();
    $creator = User::factory()->create(['department_id' => $dept->id]);
    $creator->assignRole('dept_staff');
    $project = Project::factory()->create([
        'department_id' => $dept->id,
        'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
        'supervisor_id' => User::factory()->create()->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
        'created_by' => $creator->id,
    ]);

    expect($project->canBeModifiedBy($creator))->toBeFalse();
});

test('canBeArchivedBy: dept_manager of same department can archive a pending project', function () {
    $dept = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $dept->id]);
    $manager->assignRole('dept_manager');
    $project = Project::factory()->create([
        'department_id' => $dept->id,
        'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
        'supervisor_id' => User::factory()->create()->id,
        'current_status_id' => Project::STATUS_PENDING,
    ]);

    expect($project->canBeArchivedBy($manager))->toBeTrue();
});

test('canBeArchivedBy: dept_manager of a different department cannot archive', function () {
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $otherDept->id]);
    $manager->assignRole('dept_manager');
    $project = Project::factory()->create([
        'department_id' => $dept->id,
        'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
        'supervisor_id' => User::factory()->create()->id,
        'current_status_id' => Project::STATUS_PENDING,
    ]);

    expect($project->canBeArchivedBy($manager))->toBeFalse();
});

test('canBeArchivedBy: cannot archive an already-archived project (idempotency guard)', function () {
    $dept = Department::factory()->create();
    $manager = User::factory()->create(['department_id' => $dept->id]);
    $manager->assignRole('dept_manager');
    $project = Project::factory()->create([
        'department_id' => $dept->id,
        'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
        'supervisor_id' => User::factory()->create()->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
    ]);

    expect($project->canBeArchivedBy($manager))->toBeFalse();
});

test('canBeArchivedBy: dept_staff can never archive', function () {
    $dept = Department::factory()->create();
    $staff = User::factory()->create(['department_id' => $dept->id]);
    $staff->assignRole('dept_staff');
    $project = Project::factory()->create([
        'department_id' => $dept->id,
        'specialization_id' => Specialization::factory()->create(['department_id' => $dept->id])->id,
        'supervisor_id' => User::factory()->create()->id,
        'current_status_id' => Project::STATUS_PENDING,
    ]);

    expect($project->canBeArchivedBy($staff))->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProjectModelTest`
Expected: FAIL — `created_by` is not a fillable attribute, `Project::STATUS_ARCHIVED`/`canBeModifiedBy`/`canBeArchivedBy` don't exist yet.

- [ ] **Step 3: Write the migration**

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
            $table->foreignId('created_by')
                ->nullable()
                ->after('current_status_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};
```

Save as `database/migrations/2026_08_23_120100_add_created_by_to_projects_table.php`.

- [ ] **Step 4: Update the Project model**

In `app/Models/Project.php`:

Add to `$fillable` (after `'current_status_id',`): `'created_by',`.

Add public constants at the top of the class body (before `$fillable`):

```php
public const STATUS_ARCHIVED = 1;
public const STATUS_PENDING  = 2;
```

Add the relationship (near `supervisor()`):

```php
public function createdBy(): BelongsTo
{
    return $this->belongsTo(User::class, 'created_by');
}
```

Add the two permission methods (near the bottom, after `basedOn()`):

```php
public function canBeModifiedBy(User $user): bool
{
    if ($user->hasRole('super_admin')) {
        return true;
    }

    if ($this->current_status_id !== self::STATUS_PENDING) {
        return false;
    }

    if ($user->hasRole('dept_manager') && $user->department_id === $this->department_id) {
        return true;
    }

    return $this->created_by === $user->id && $user->department_id === $this->department_id;
}

public function canBeArchivedBy(User $user): bool
{
    if ($user->hasRole('super_admin')) {
        return true;
    }

    return $user->hasRole('dept_manager')
        && $user->department_id === $this->department_id
        && $this->current_status_id === self::STATUS_PENDING;
}
```

`User` is already imported implicitly via other relations returning `User::class`; add `use App\Models\User;`? No — `Project.php` is itself in `App\Models`, so `User` needs no import (same namespace). Only the type-hint `User $user` in the two new method signatures needs it — since `User` lives in the same `App\Models` namespace as `Project`, no `use` statement is required.

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=ProjectModelTest`
Expected: PASS (9 new tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_23_120100_add_created_by_to_projects_table.php app/Models/Project.php tests/Feature/Models/ProjectModelTest.php
git commit -m "feat: add created_by to projects and centralize modify/archive permission checks on Project"
```

---

### Task 3: Wire `created_by` into project creation

**Files:**
- Modify: `app/Http/Controllers/ProjectController.php`
- Test: `tests/Feature/Project/ProjectTest.php`

**Interfaces:**
- Consumes: `Auth::user()->id` (already used elsewhere in the same controller).
- Produces: every `Project::create()` call sets `created_by`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Project/ProjectTest.php`, near the other create tests:

```php
test('created project records the creating user as created_by', function () {
    $deps = makeProjectDeps();
    $manager = userWithRole('dept_manager');

    $this->actingAs($manager)
        ->post(route('projects.store'), projectData($deps))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'project_title' => 'Test Project Title',
        'created_by'    => $manager->id,
    ]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter="created project records the creating user"`
Expected: FAIL — `created_by` column is `null` in the assertion.

- [ ] **Step 3: Update `ProjectController::store()`**

In `app/Http/Controllers/ProjectController.php`, in the `Project::create([...])` array inside `store()`, add `'created_by' => $user->id,` right after `'current_status_id' => $statusId,`. Also replace the two private constants `STATUS_ARCHIVED`/`STATUS_PENDING` at the top of the class with references to `Project::STATUS_ARCHIVED`/`Project::STATUS_PENDING` everywhere they're used in this file (`store()`, `update()`'s `is_final` check, `approve()`), and delete the `private const STATUS_ARCHIVED = 1; private const STATUS_PENDING = 2;` lines — the single source of truth is now on the model (Task 2).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter="created project records the creating user"`
Expected: PASS.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: still 220+ passing (no regressions — every other `self::STATUS_*` usage in the file was mechanically replaced with `Project::STATUS_*`, same values).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ProjectController.php tests/Feature/Project/ProjectTest.php
git commit -m "feat: record created_by on project creation; use Project::STATUS_* constants"
```

---

### Task 4: Enforce "replace/delete only while مقترح, only creator or dept_manager" on update/destroy

**Files:**
- Modify: `app/Http/Requests/UpdateProjectRequest.php`
- Create: `app/Http/Requests/DeleteProjectRequest.php`
- Modify: `app/Http/Controllers/ProjectController.php`
- Modify: `tests/Feature/Roles/RoleVerificationTest.php`
- Test: `tests/Feature/Project/ProjectTest.php`

**Interfaces:**
- Consumes: `Project::canBeModifiedBy(User $user): bool` from Task 2.
- Produces: `update()`/`destroy()`/`edit()` all 403 consistently via the same predicate.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Project/ProjectTest.php`:

```php
test('creator can replace their own pending project details', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $staff = User::factory()->create(['department_id' => $dept->id]);
    $staff->assignRole('dept_staff');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
        'created_by'        => $staff->id,
    ]);

    $this->actingAs($staff)
        ->put(route('projects.update', $project->id), projectData($deps, ['project_title' => 'Replaced Title']))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'project_title' => 'Replaced Title']);
});

test('non-creator dept_staff cannot replace another staff member pending project', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $creator = User::factory()->create(['department_id' => $dept->id]);
    $otherStaff = User::factory()->create(['department_id' => $dept->id]);
    $otherStaff->assignRole('dept_staff');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
        'created_by'        => $creator->id,
    ]);

    $this->actingAs($otherStaff)
        ->put(route('projects.update', $project->id), projectData($deps))
        ->assertForbidden();
});

test('dept_manager cannot replace an archived project details', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $manager = User::factory()->create(['department_id' => $dept->id]);
    $manager->assignRole('dept_manager');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
    ]);

    $this->actingAs($manager)
        ->put(route('projects.update', $project->id), projectData($deps))
        ->assertForbidden();
});

test('super_admin can replace an archived project details', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->put(route('projects.update', $project->id), projectData($deps, ['project_title' => 'Admin Replaced']))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'project_title' => 'Admin Replaced']);
});

test('creator can delete their own pending project', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $staff = User::factory()->create(['department_id' => $dept->id]);
    $staff->assignRole('dept_staff');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
        'created_by'        => $staff->id,
    ]);

    $this->actingAs($staff)
        ->delete(route('projects.destroy', $project->id))
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'is_deleted' => true]);
});

test('dept_manager cannot delete an archived project', function () {
    $dept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $manager = User::factory()->create(['department_id' => $dept->id]);
    $manager->assignRole('dept_manager');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
    ]);

    $this->actingAs($manager)
        ->delete(route('projects.destroy', $project->id))
        ->assertForbidden();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'is_deleted' => false]);
});
```

Add `use App\Models\Project;` at the top of `ProjectTest.php` if not already imported (check first — `ProjectTest.php` already uses `Project::factory()` per Step 1 audit, so it's likely already imported).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProjectTest`
Expected: FAIL on the 6 new tests — current `authorizeEdit()`/`destroy()` don't check ownership or lock archived projects.

- [ ] **Step 3: Update `UpdateProjectRequest`**

Replace `app/Http/Requests/UpdateProjectRequest.php`'s `authorize()`:

```php
public function authorize(): bool
{
    $project = Project::where('is_deleted', false)->find($this->route('project'));

    if (! $project) {
        return true; // let the controller's findOrFail produce a 404
    }

    return $project->canBeModifiedBy($this->user());
}
```

Add `use App\Models\Project;` to the file's imports.

- [ ] **Step 4: Create `DeleteProjectRequest`**

```php
<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class DeleteProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::where('is_deleted', false)->find($this->route('project'));

        if (! $project) {
            return true;
        }

        return $project->canBeModifiedBy($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
```

Save as `app/Http/Requests/DeleteProjectRequest.php`.

- [ ] **Step 5: Update `ProjectController`**

In `app/Http/Controllers/ProjectController.php`:

Delete the entire `authorizeEdit()` private method.

In `edit(int $id)`, replace `$this->authorizeEdit($project);` with:

```php
if (! $project->canBeModifiedBy(Auth::user())) {
    abort(403);
}
```

In `update(UpdateProjectRequest $request, int $id)`, replace `$this->authorizeEdit($project);` the same way (or simply delete the call — `UpdateProjectRequest::authorize()` from Step 3 already ran before the controller method executes, making the controller-side check redundant; delete the line entirely from `update()`).

Change `destroy()`'s signature and body:

```php
public function destroy(DeleteProjectRequest $request, int $id): RedirectResponse
{
    $project = Project::where('is_deleted', false)->findOrFail($id);

    $project->update(['is_deleted' => true]);

    return redirect()->route('projects.index')
        ->with('success', 'تم حذف المشروع بنجاح');
}
```

Add `use App\Http\Requests\DeleteProjectRequest;` to the imports.

- [ ] **Step 6: Run the new tests**

Run: `php artisan test --filter=ProjectTest`
Expected: PASS (all 6 new tests).

- [ ] **Step 7: Run the full suite and fix intentionally-broken fixtures**

Run: `php artisan test`
Expected failures (pre-existing tests whose fixtures predate ownership/lock rules):
- `dept_manager can soft-delete a project` (`tests/Feature/Roles/RoleVerificationTest.php`) — currently deletes an **archived** project and expects success; that's now forbidden.
- `dept_staff can edit their own pending project in same department` (same file) — currently never sets `created_by`, so the (correct) new ownership check now rejects it.

Fix both in `tests/Feature/Roles/RoleVerificationTest.php`:

1. Update the `pendingRvProject()`/`archivedRvProject()` helper signatures to accept an optional creator:

```php
function pendingRvProject(int $deptId, int $specId, int $supervisorId, ?int $createdBy = null): Project
{
    return Project::factory()->create([
        'department_id'     => $deptId,
        'specialization_id' => $specId,
        'supervisor_id'     => $supervisorId,
        'current_status_id' => 2, // proposal_submitted
        'created_by'        => $createdBy,
        'is_deleted'        => false,
    ]);
}

function archivedRvProject(int $deptId, int $specId, int $supervisorId, ?int $createdBy = null): Project
{
    return Project::factory()->create([
        'department_id'     => $deptId,
        'specialization_id' => $specId,
        'supervisor_id'     => $supervisorId,
        'current_status_id' => 1, // archived
        'created_by'        => $createdBy,
        'is_deleted'        => false,
    ]);
}
```

2. Fix `dept_staff can edit their own pending project in same department` (create the staff first, pass their id as creator):

```php
test('dept_staff can edit their own pending project in same department', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $staff   = staffInDept($dept->id);
    $project = pendingRvProject($dept->id, $spec->id, $sv->id, $staff->id);

    $this->actingAs($staff)
        ->get(route('projects.edit', $project->id))
        ->assertOk();
});
```

3. Fix `dept_manager can soft-delete a project` — change the fixture from `archivedRvProject` to `pendingRvProject` (a `dept_manager` deleting a pending project in their own department is still allowed; deleting an archived one is not — that's the whole point of the new rule):

```php
test('dept_manager can soft-delete a pending project', function () {
    ['dept' => $dept, 'spec' => $spec, 'supervisor' => $sv] = rvSetup();
    $project = pendingRvProject($dept->id, $spec->id, $sv->id);
    $mgr     = managerInDept($dept->id);

    $this->actingAs($mgr)
        ->delete(route('projects.destroy', $project->id))
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'is_deleted' => true]);
});
```

(Renamed the test description to `...a pending project` since that's now the accurate claim; `super_admin can soft-delete a project` at line ~141 needs no change — `super_admin` bypasses the status check by design, and it already targets an archived project, which is exactly the case only `super_admin` should be able to delete.)

- [ ] **Step 8: Run the full suite again**

Run: `php artisan test`
Expected: all green, no failures.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/UpdateProjectRequest.php app/Http/Requests/DeleteProjectRequest.php app/Http/Controllers/ProjectController.php tests/Feature/Project/ProjectTest.php tests/Feature/Roles/RoleVerificationTest.php
git commit -m "feat: lock replace/delete to pending status, creator, or department manager"
```

---

### Task 5: Rename approve → archive, scope it to the manager's own department, guard against re-archiving

**Files:**
- Create: `app/Http/Requests/ArchiveProjectRequest.php`
- Modify: `app/Http/Controllers/ProjectController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Project/ProjectTest.php`
- Modify: `tests/Feature/Roles/RoleVerificationTest.php`

**Interfaces:**
- Consumes: `Project::canBeArchivedBy(User $user): bool` from Task 2.
- Produces: `POST projects/{id}/archive` (route name `projects.archive`) replacing `projects.approve`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Project/ProjectTest.php`:

```php
test('dept_manager can archive a pending project in their own department', function () {
    $deps = makeProjectDeps();
    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
    ]);

    $this->actingAs(userWithRole('dept_manager'))
        ->post(route('projects.archive', $project->id))
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'current_status_id' => Project::STATUS_ARCHIVED]);
});

test('dept_manager of a different department cannot archive a pending project', function () {
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $deps = makeProjectDeps($dept);
    $manager = User::factory()->create(['department_id' => $otherDept->id]);
    $manager->assignRole('dept_manager');

    $project = Project::factory()->create([
        'department_id'     => $dept->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
    ]);

    $this->actingAs($manager)
        ->post(route('projects.archive', $project->id))
        ->assertForbidden();
});

test('cannot archive an already-archived project', function () {
    $deps = makeProjectDeps();
    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_ARCHIVED,
    ]);

    $this->actingAs(userWithRole('dept_manager'))
        ->post(route('projects.archive', $project->id))
        ->assertForbidden();
});

test('dept_staff cannot archive a project', function () {
    $deps = makeProjectDeps();
    $project = Project::factory()->create([
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'current_status_id' => Project::STATUS_PENDING,
    ]);
    $staff = User::factory()->create(['department_id' => $deps['dept']->id]);
    $staff->assignRole('dept_staff');

    $this->actingAs($staff)
        ->post(route('projects.archive', $project->id))
        ->assertForbidden();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProjectTest`
Expected: FAIL — route `projects.archive` doesn't exist yet.

- [ ] **Step 3: Create `ArchiveProjectRequest`**

```php
<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class ArchiveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = Project::where('is_deleted', false)->find($this->route('id'));

        if (! $project) {
            return true;
        }

        return $project->canBeArchivedBy($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
```

Save as `app/Http/Requests/ArchiveProjectRequest.php`. Note: this route keeps `{id}` (not `{project}`) as its wildcard, matching the existing custom-route convention already used for `assign-examiner`/`evaluation`/`score` in `routes/web.php`.

- [ ] **Step 4: Rename the controller method**

In `app/Http/Controllers/ProjectController.php`, replace:

```php
public function approve(int $id): RedirectResponse
{
    $project = Project::where('is_deleted', false)->findOrFail($id);

    $project->update(['current_status_id' => self::STATUS_ARCHIVED]);

    return back()->with('success', 'تم اعتماد المشروع بنجاح');
}
```

with:

```php
public function archive(ArchiveProjectRequest $request, int $id): RedirectResponse
{
    $project = Project::where('is_deleted', false)->findOrFail($id);

    $project->update(['current_status_id' => Project::STATUS_ARCHIVED]);

    return back()->with('success', 'تم أرشفة المشروع بنجاح');
}
```

Add `use App\Http\Requests\ArchiveProjectRequest;` to the imports.

- [ ] **Step 5: Update the route**

In `routes/web.php`, replace:

```php
Route::post('projects/{id}/approve', [ProjectController::class, 'approve'])
    ->middleware('role:dept_manager,super_admin')
    ->name('projects.approve');
```

with:

```php
Route::post('projects/{id}/archive', [ProjectController::class, 'archive'])
    ->name('projects.archive');
```

(The `role:` middleware is dropped — `ArchiveProjectRequest::authorize()` now fully subsumes it, including the department scoping the old middleware never had.)

- [ ] **Step 6: Run the new tests**

Run: `php artisan test --filter=ProjectTest`
Expected: PASS.

- [ ] **Step 7: Fix references to the old route/test names**

`tests/Feature/Roles/RoleVerificationTest.php` and `tests/Feature/Project/ProjectTest.php` both call `route('projects.approve', ...)` in several existing tests (`super_admin can approve a pending project`, `dept_manager can approve a pending project`, `dept_staff cannot approve a project`, `dept_manager can approve pending project`, `dept_staff cannot approve project`). Rename every `route('projects.approve', ...)` call to `route('projects.archive', ...)` in both files (the assertions themselves — status becomes 1, or 403 for disallowed roles — stay correct as-is since `canBeArchivedBy()` preserves the same role gate the old middleware had, plus adds department scoping which none of these particular fixtures violate). Renaming the test description strings themselves (e.g. `'dept_manager can approve a pending project'` → `'dept_manager can archive a pending project'`) is optional polish — do it for the ones you touch, to keep test names truthful.

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: all green.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/ArchiveProjectRequest.php app/Http/Controllers/ProjectController.php routes/web.php tests/Feature/Project/ProjectTest.php tests/Feature/Roles/RoleVerificationTest.php
git commit -m "feat: rename approve to archive, scope to manager's own department, block re-archiving"
```

---

### Task 6: Vue — ownership/status-aware actions on Show.vue and Index.vue

`resources/js/pages/Projects/Create.vue` is intentionally **not** touched here — Step 1's audit
confirmed it already is the "submit proposal" form the spec asks for (full form + PDF upload,
already gated server-side to `dept_staff`/`dept_manager`/`super_admin` via `StoreProjectRequest`).
Nothing about the paper-approval change affects who can create a proposal or what that form looks
like.

**Files:**
- Modify: `resources/js/pages/Projects/Show.vue`
- Modify: `resources/js/pages/Projects/Index.vue`

**Interfaces:**
- Consumes: `page.props.auth.user.id`, `page.props.auth.user.department_id` (already typed in `resources/js/types/index.ts`); `project.created_by`, `project.department_id` (new — these already exist as raw DB columns returned by Eloquent's default `toArray()`, no backend select-list changes needed per the Step 1 audit of `SearchService`/`ProjectController::show()`, which don't restrict columns).
- Produces: `canReplace`, `canDelete`, `canArchive` — replacing the old blanket `canEdit`/`canDelete`/`canApprove`.

- [ ] **Step 1: Update the `Project` TypeScript interface in `Show.vue`**

Add `created_by: number | null;` and `department_id: number;` to the `Project` interface in `resources/js/pages/Projects/Show.vue` (alongside the existing `id`, `project_title`, etc.).

- [ ] **Step 2: Replace the permission computeds in `Show.vue`**

Replace:

```ts
const canEdit   = computed(() => ['dept_staff', 'dept_manager', 'super_admin'].includes(userRole.value));
const canDelete = computed(() => ['dept_manager', 'super_admin'].includes(userRole.value));
const canManage = computed(() => ['dept_manager', 'super_admin'].includes(userRole.value));
const canApprove = computed(() =>
    ['dept_manager', 'super_admin'].includes(userRole.value)
    && isPendingApproval(props.project.current_status?.status_name)
);
```

with:

```ts
const currentUser = computed(() => page.props.auth.user);
const isPending    = computed(() => isPendingApproval(props.project.current_status?.status_name));
const canManage    = computed(() => ['dept_manager', 'super_admin'].includes(userRole.value));

const canModify = computed(() => {
    if (userRole.value === 'super_admin') return true;
    if (!isPending.value) return false;
    if (userRole.value === 'dept_manager' && currentUser.value.department_id === props.project.department_id) return true;
    return props.project.created_by === currentUser.value.id && currentUser.value.department_id === props.project.department_id;
});

const canReplace = canModify;
const canDelete  = canModify;

const canArchive = computed(() =>
    userRole.value === 'super_admin'
    || (userRole.value === 'dept_manager' && isPending.value && currentUser.value.department_id === props.project.department_id)
);
```

Note this mirrors `Project::canBeModifiedBy()`/`canBeArchivedBy()` exactly — it's UI-layer defense in depth; the backend FormRequests are the actual enforcement.

- [ ] **Step 3: Update the action functions and template**

Rename `approveProject()` → `archiveProject()`:

```ts
function archiveProject() {
    router.post(route('projects.archive', [props.project.id]));
}
```

In the template, change every `canApprove` → `canArchive`, `approveProject` → `archiveProject`, `canEdit` → `canReplace` (the "تعديل"/edit link), `"اعتماد المشروع"` → `"أرشفة المشروع"` (the button label). The existing `canDelete` reference in the template needs no rename (variable name unchanged, only its computed logic changed in Step 2).

- [ ] **Step 4: Same treatment for `Index.vue`**

Add `created_by: number | null;` and `department_id: number;` to `Index.vue`'s `Project` interface. Replace the `canEdit(p)`/`canDelete` (currently a plain boolean, not per-row)/`canApprove(p)` functions with per-row equivalents mirroring Step 2's logic (as functions taking `p: Project` instead of computed refs, since `Index.vue` renders a list):

```ts
function canModify(p: Project): boolean {
    if (userRole.value === 'super_admin') return true;
    if (!isPendingApproval(p.current_status?.status_name)) return false;
    const user = page.props.auth.user;
    if (userRole.value === 'dept_manager' && user.department_id === p.department_id) return true;
    return p.created_by === user.id && user.department_id === p.department_id;
}

function canReplace(p: Project): boolean {
    return canModify(p);
}

function canDeleteProject(p: Project): boolean {
    return canModify(p);
}

function canArchive(p: Project): boolean {
    const user = page.props.auth.user;
    return userRole.value === 'super_admin'
        || (userRole.value === 'dept_manager' && isPendingApproval(p.current_status?.status_name) && user.department_id === p.department_id);
}
```

Rename `approveProject(id)` → `archiveProject(id)` (posting to `route('projects.archive', [id])`). Update the template: `canEdit(project)` → `canReplace(project)`, `canApprove(project)` → `canArchive(project)`, `approveProject(project.id)` → `archiveProject(project.id)`, the plain `canDelete` boolean reference → `canDeleteProject(project)`, `"اعتماد"` → `"أرشفة"`.

- [ ] **Step 5: Type-check and build**

Run: `npm run build`
Expected: builds cleanly with no TypeScript errors (confirms the new `Project` interface fields and renamed functions/template bindings are all consistent).

- [ ] **Step 6: Manual verification in the browser**

Per the project's own testing conventions, start the dev server and manually verify in a browser: log in as `dept_staff`, create a proposal, confirm "Replace"/"Delete" show on it and work; log in as a `dept_manager` in a different department and confirm those buttons are hidden/403 on that same proposal; archive it as the correct `dept_manager`; confirm Replace/Delete/Archive all disappear once archived (for non-`super_admin`).

- [ ] **Step 7: Commit**

```bash
git add resources/js/pages/Projects/Show.vue resources/js/pages/Projects/Index.vue
git commit -m "feat: make replace/delete/archive actions ownership- and status-aware in the UI"
```

---

### Task 7: Full suite verification pass

**Files:** none (verification only, fixes land wherever the failure is).

- [ ] **Step 1: Run the complete suite**

Run: `php artisan test`
Expected: 100% pass. If anything fails, use superpowers:systematic-debugging — find the root cause (likely a fixture still missing `created_by`, or a route-name reference to `projects.approve` missed in Task 5 Step 7) rather than patching around it.

- [ ] **Step 2: Run the frontend build once more**

Run: `npm run build`
Expected: clean build (re-confirms Task 6 after any Task 7 Step 1 fixes touched Vue files).

- [ ] **Step 3: Record the final pass/fail count for the final report.** No commit for this task — it's a checkpoint, not a change (unless Step 1 required a fix, in which case commit that fix with a message describing the actual root cause found).

---

### Task 8: Update CLAUDE.md and PROGRESS.md

**Files:**
- Modify: `CLAUDE.md`
- Modify: `PROGRESS.md`

- [ ] **Step 1: Update CLAUDE.md's "Key Business Rules" section**

Replace the two approval-gate bullets added by the prior lifecycle-scope-down work:

```
- Two approval gates — supervisor approval and department approval — are tracked as fields on
  `projects` (`supervisor_approved_by/_at`, `department_approved_by/_at`), not as separate
  lifecycle statuses. Both nullable; `Project::isSupervisorApproved()`/`isDepartmentApproved()`
  check the `_at` timestamp.
```

with:

```
- Supervisor and department approval happen on paper, outside the system — there is no digital
  approval tracking. A project created by dept_manager/dept_staff is implicitly pre-approved by
  their role; the system's only job is to gate what can happen to it while it's مقترح (pending):
  - Replace details/file or soft-delete: only the creator (`projects.created_by`) or the
    dept_manager of that department, and only while current_status_id = 2 (مقترح). super_admin
    bypasses both the status and ownership checks.
  - Archive (مقترح → مؤرشف): only a dept_manager of that same department (or super_admin), only
    while current_status_id = 2. Once مؤرشف, replace/delete are locked for everyone except
    super_admin. See `Project::canBeModifiedBy()`/`canBeArchivedBy()` for the single source of
    truth, and `UpdateProjectRequest`/`DeleteProjectRequest`/`ArchiveProjectRequest` for where
    it's enforced.
```

- [ ] **Step 2: Update the "Database — 13 Tables" section note**

Find the note added by the prior lifecycle-scope-down work:

```
Note: the project lifecycle has been scoped down to 2 statuses (see "Key Business Rules" below).
DEFENSE and STATUS_HISTORY were never implemented — planning-only, now removed from scope entirely.
```

Append a new line after it:

```
`supervisor_approved_by/_at` and `department_approved_by/_at` (added 2026-08-17) were removed on
2026-08-23 — approval turned out to happen on paper, outside the system, so the fields tracked
nothing any controller or UI ever read. `projects.created_by` (FK to users, nullable) was added
in their place to support the creator-or-department-manager permission check above.
```

- [ ] **Step 3: Add a "Current Status" entry**

Prepend a new bullet at the top of the `## Current Status` list (above the `Project Lifecycle Scope-Down` entry):

```
- ✅ Paper-Approval Proposal Lifecycle — [N]/[N] total suite ([fill in from Task 7's final run])
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
  - tests/Feature/Project/ProjectTest.php — [N] new tests covering the full create/replace/
    delete/archive permission matrix
  - tests/Feature/Roles/RoleVerificationTest.php — updated 2 fixtures whose old expectations
    (dept_manager deleting an archived project; dept_staff editing a project with no created_by
    set) were superseded by the new rules; renamed projects.approve references to projects.archive
```

Fill in the `[N]` placeholders with the real numbers once Task 7 has run.

- [ ] **Step 4: Show the diff to the user (do not commit yet)**

Run: `git diff CLAUDE.md PROGRESS.md`

Per the user's explicit instruction on this feature ("Do not merge to main — leave that decision to me"), commit this doc update along with the rest of the work, but do not merge the branch.

- [ ] **Step 5: Commit**

```bash
git add CLAUDE.md PROGRESS.md
git commit -m "docs: reflect paper-based approval and the new proposal lifecycle permission matrix"
```

---

### Task 9: Self-review

- [ ] **Step 1:** Run superpowers:verification-before-completion — re-run `php artisan test` and `npm run build` fresh (don't reuse Task 7's output as evidence) and confirm the actual current state before claiming anything is done.
- [ ] **Step 2:** Run `/code-review` on the full branch diff against `main`. Fix any real (CONFIRMED/high-confidence PLAUSIBLE) finding by editing the code and re-running the affected tests. For anything you disagree with, don't silently dismiss it — note it explicitly for the final report.
- [ ] **Step 3:** Do not merge to `main` — the user explicitly reserved that decision.
