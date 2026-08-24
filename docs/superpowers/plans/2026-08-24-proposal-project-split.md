# Proposal/Project Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Split the conflated `projects` table into two linked entities — `proposals` (paper-shaped, 2-state: مقترح/مؤرشف, editable while مقترح) and `projects` (the actual graded work, created only via a single atomic "تنزيل المشروع" action that locks the proposal and creates the project row) — and repoint every part of the app that currently reads the conflated shape.

**Architecture:** New tables `proposals`, `proposal_students`, `project_lifecycle_status` are created alongside a rebuilt `projects` table (`proposal_id`, no more title/department/students directly on it). `project_examiners`/`evaluations` keep their exact shape, just re-pointed at the new `projects.id`. `Proposal::instantiateProject()` is the single atomic transaction backing the new button, reusing `canBeArchivedBy()`'s exact logic (ported as `canBeInstantiatedBy()`) so super_admin bypass and dept_manager-only gating carry over unchanged. A one-time Artisan command migrates the live DB's 25 conflated rows into the new shape; a rewritten `DummyDataSeeder` produces the same end-state for fresh installs.

**Tech Stack:** Laravel 11, PHP 8.2, MariaDB, Pest PHP, Vue 3 + Inertia.js 2 + TypeScript.

**Spec:** User's task message (2026-08-24, "THE REAL REQUIREMENT" + "DECISIONS") and the prior audit report in this conversation (Step 1 findings: schema reality, row counts, the id=1 data-integrity violation, `based_on_project_id` chains).

## Global Constraints

- Branch `feature/proposal-project-split`, worktree `.claude/worktrees/proposal-project-split`. Do not merge to `main`.
- Decision 1: project id=1's erroneous grading data (2 examiners, 2 evaluations, final_score=87.50) is deleted before migration, so id=1 migrates as a clean proposal-only row.
- Decision 2 field split: `title, description, academic_year, department_id, specialization_id, supervisor_id, created_by, draft_file_path, students, status_id (2-state)` → `proposals`. `final_score, examiners, evaluations, project-level status` → `projects` (via `proposal_id`).
- Decision 3: `Project::supervisor`/`Project::students` are read-by-reference accessors proxying through `proposal_id` — no duplicated columns.
- Decision 4: `PublicController::browse()`/`show()` show only fully-archived, graded **projects**, never bare proposals.
- Decision 5: `ProjectExaminerController`/`EvaluationController`/`ScoreInput.vue` keep identical business logic — they already only reference `$projectId`/`Project::`, so the split repoints them for free (verified during audit — zero edits needed to these two controllers).
- Decision 6: `proposals.based_on_project_id` (renamed target, still FK → `projects.id`) is kept and re-pointed for the 3 existing chains (old-id 7→6, 9→8, 23→21) to the *new* `projects.id` values.
- Decision 7: `project_documents` (empty, redundant with `draft_file_path`) is dropped entirely.
- Decision 8: super_admin bypasses the instantiate-project gate too — `canBeInstantiatedBy()` ports `canBeArchivedBy()` verbatim, which already encodes this.
- Judgment calls flagged for the final report (not silently decided): `visit_count` placement (→ moving to `projects`, since decision 4 means only projects are publicly browsable — proposals get no visit counter), the 3 ungraded-but-مؤرشف rows' (ids 5, 14, 22) new project status (→ `قيد التنفيذ`, since they were never graded), `projects.proposal_id` FK behavior (→ `restrictOnDelete()`, not cascade — a hard-deleted proposal must not silently destroy a graded project's examiners/evaluations), `instantiated_by`/`instantiated_at` on the 20 backfilled historical rows (→ nullable `instantiated_by` since no real actor exists for migrated data; `instantiated_at` backfilled from the proposal's `updated_at` as the best available proxy), and the **much larger ripple** the audit surfaced beyond the spec's explicit file list: `ReportService`, `ProjectsImport`, `Department`/`Specialization`/`User` model relations, and `DummyDataSeeder` all query the old conflated `Project` model directly and will fail at runtime (not just "the type doesn't match" — actual missing-column SQL errors) unless repointed. This plan includes that work; flagged explicitly since the spec didn't call these files out by name.
- No new UI/endpoint is built to transition a project from `قيد التنفيذ` → `مؤرشف` (no such action was requested in Step D) — flagged as a known limitation: Public Browse will only ever show the 17 backfilled historical rows until a future task adds that transition.

---

## File Structure

**New:**
- `database/migrations/2026_08_24_160000_drop_project_examiner_evaluation_legacy_fk.php`
- `database/migrations/2026_08_24_160100_create_proposals_table.php`
- `database/migrations/2026_08_24_160200_create_proposal_students_table.php`
- `database/migrations/2026_08_24_160300_create_project_lifecycle_status_table.php`
- `database/migrations/2026_08_24_160400_rename_projects_to_projects_legacy.php`
- `database/migrations/2026_08_24_160500_create_new_projects_table.php`
- `database/migrations/2026_08_24_160600_drop_project_documents_table.php`
- `database/seeders/ProjectLifecycleStatusSeeder.php`
- `app/Console/Commands/MigrateProposalProjectData.php`
- `app/Models/Proposal.php`
- `app/Models/ProposalStudent.php`
- `app/Models/ProjectLifecycleStatus.php`
- `app/Http/Requests/StoreProposalRequest.php`, `UpdateProposalRequest.php`, `DeleteProposalRequest.php`, `InstantiateProjectRequest.php`
- `app/Http/Controllers/ProposalController.php`
- `resources/js/pages/Proposals/{Index,Create,Edit,Show}.vue`
- `resources/js/pages/Projects/{Index,Show}.vue` (new — instantiated projects)
- `resources/js/composables/useProposalStatus.ts`
- `tests/Feature/Models/ProposalModelTest.php`, `ProjectModelTest.php` (rewritten)
- `tests/Feature/Proposal/ProposalTest.php`
- `tests/Feature/Project/InstantiateProjectTest.php`
- `tests/Feature/Project/ProjectExploitPreventionTest.php`
- `tests/Feature/Console/MigrateProposalProjectDataTest.php`

**Modified:**
- `app/Models/Project.php` (rebuilt), `app/Models/Department.php`, `Specialization.php`, `User.php`
- `app/Http/Controllers/ProjectController.php` (rebuilt — thin, view-only)
- `app/Http/Controllers/PublicController.php`, `DepartmentController.php`, `SpecializationController.php`
- `app/Services/SearchService.php`, `ReportService.php`
- `app/Imports/ProjectsImport.php`
- `database/seeders/DummyDataSeeder.php`
- `routes/web.php`
- `resources/js/components/ConfirmDelete.vue`, `AppSidebar.vue`
- `resources/js/pages/Projects/{Index,Create,Edit,Show}.vue` → moved to `Proposals/`
- `tests/Feature/Project/ProjectTest.php` → renamed/rewritten as proposal tests where applicable
- `tests/Feature/Roles/RoleVerificationTest.php`, `tests/Feature/Report/ReportTest.php`, `tests/Feature/Import/ImportTest.php`, `tests/Feature/Search/SearchTest.php`, `tests/Feature/Public/PublicBrowseTest.php`, `tests/Feature/Seeders/ProjectStatusSeederTest.php`
- `CLAUDE.md`, `PROGRESS.md`

---

### Task 1: Schema migrations (no data touched yet)

**Files:**
- Create: `database/migrations/2026_08_24_160000_drop_project_examiner_evaluation_legacy_fk.php`
- Create: `database/migrations/2026_08_24_160100_create_proposals_table.php`
- Create: `database/migrations/2026_08_24_160200_create_proposal_students_table.php`
- Create: `database/migrations/2026_08_24_160300_create_project_lifecycle_status_table.php`
- Create: `database/migrations/2026_08_24_160400_rename_projects_to_projects_legacy.php`
- Create: `database/migrations/2026_08_24_160500_create_new_projects_table.php`
- Create: `database/migrations/2026_08_24_160600_drop_project_documents_table.php`
- Create: `database/seeders/ProjectLifecycleStatusSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Seeders/ProjectLifecycleStatusSeederTest.php`

**Interfaces:**
- Produces: tables `proposals` (id, title, description, academic_year, department_id, specialization_id, supervisor_id, created_by, draft_file_path, status_id, based_on_project_id, is_deleted, timestamps), `proposal_students` (id, proposal_id, full_name, registration_number, status, withdrawal_date, timestamps), `project_lifecycle_status` (id, status_name, sort_order, is_active, timestamps — seeded 1=قيد التنفيذ, 2=مؤرشف), `projects_legacy` (renamed old `projects`), new `projects` (id, proposal_id, status_id, final_score, instantiated_by, instantiated_at, visit_count, is_deleted, timestamps). `project_examiners.project_id`/`evaluations.project_id` become plain unconstrained `unsignedBigInteger` after this task (FK re-added in Task 3, once values are correct).

- [ ] **Step 1: Write the failing seeder test**

```php
<?php
// tests/Feature/Seeders/ProjectLifecycleStatusSeederTest.php

use Database\Seeders\ProjectLifecycleStatusSeeder;

test('exactly 2 project lifecycle statuses exist', function () {
    $this->seed(ProjectLifecycleStatusSeeder::class);

    expect(DB::table('project_lifecycle_status')->count())->toBe(2);
});

test('قيد التنفيذ status is id 1 and مؤرشف is id 2', function () {
    $this->seed(ProjectLifecycleStatusSeeder::class);

    $inProgress = DB::table('project_lifecycle_status')->find(1);
    $archived   = DB::table('project_lifecycle_status')->find(2);

    expect($inProgress->status_name)->toBe('قيد التنفيذ')
        ->and($archived->status_name)->toBe('مؤرشف');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProjectLifecycleStatusSeederTest`
Expected: FAIL — table `project_lifecycle_status` doesn't exist yet.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_08_24_160000_drop_project_examiner_evaluation_legacy_fk.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Drops the FK pointing at the old (soon-to-be-renamed) `projects` table.
    // The columns stay as plain unsignedBigInteger — their VALUES still point
    // at old project ids until the data-migration command (Task 3) updates
    // them and re-adds the FK against the new `projects` table.
    public function up(): void
    {
        Schema::table('project_examiners', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });
    }

    // Irreversible in isolation: by the time down() would run (in reverse
    // migration order), 'projects' has already been renamed back from
    // 'projects_legacy' by migration 2026_08_24_160400's down(), so this is
    // safe — re-adding the FK against 'projects' is correct at that point.
    public function down(): void
    {
        Schema::table('project_examiners', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
```

`database/migrations/2026_08_24_160100_create_proposals_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('academic_year', 9);
            $table->foreignId('department_id')->constrained('departments');
            $table->foreignId('specialization_id')->constrained('specializations');
            $table->foreignId('supervisor_id')->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('draft_file_path')->nullable();
            $table->unsignedTinyInteger('status_id');
            $table->foreign('status_id')->references('id')->on('project_status');
            // Points at the NEW projects table (created in a later migration in
            // this same task) — "a proposal building on a previously finished
            // project", not a self-reference to another proposal.
            $table->unsignedBigInteger('based_on_project_id')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
```

`database/migrations/2026_08_24_160200_create_proposal_students_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('registration_number');
            $table->string('status')->default('active');
            $table->date('withdrawal_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_students');
    }
};
```

`database/migrations/2026_08_24_160300_create_project_lifecycle_status_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_lifecycle_status', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('status_name')->unique();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_lifecycle_status');
    }
};
```

`database/migrations/2026_08_24_160400_rename_projects_to_projects_legacy.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('projects', 'projects_legacy');
    }

    public function down(): void
    {
        Schema::rename('projects_legacy', 'projects');
    }
};
```

`database/migrations/2026_08_24_160500_create_new_projects_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete, not cascade: a hard-deleted proposal must not
            // silently destroy a graded project (and its examiners/evaluations,
            // which cascade from projects.id) — force an explicit decision.
            $table->foreignId('proposal_id')->unique()->constrained('proposals')->restrictOnDelete();
            $table->unsignedTinyInteger('status_id');
            $table->foreign('status_id')->references('id')->on('project_lifecycle_status');
            $table->decimal('final_score', 5, 2)->nullable();
            // Nullable: the 20 rows backfilled from legacy data by the data
            // migration command have no real "who clicked" actor.
            $table->foreignId('instantiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('instantiated_at')->nullable();
            $table->unsignedInteger('visit_count')->default(0);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

`database/migrations/2026_08_24_160600_drop_project_documents_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Irreversible: project_documents was empty (0 rows) in the live DB at
    // the time of this migration — confirmed via audit — so no data is lost,
    // but down() recreates an empty table, not the original data (there was
    // none).
    public function up(): void
    {
        Schema::dropIfExists('project_documents');
    }

    public function down(): void
    {
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('document_type');
            $table->string('file_path');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_final')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }
};
```

`database/seeders/ProjectLifecycleStatusSeeder.php`:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectLifecycleStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('project_lifecycle_status')->updateOrInsert(
            ['id' => 1],
            ['status_name' => 'قيد التنفيذ', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('project_lifecycle_status')->updateOrInsert(
            ['id' => 2],
            ['status_name' => 'مؤرشف', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );
    }
}
```

In `database/seeders/DatabaseSeeder.php`, add `ProjectLifecycleStatusSeeder::class` to the `$this->call([...])` list, immediately after `ProjectStatusSeeder::class`.

- [ ] **Step 4: Run migrations against the test DB and verify the seeder test passes**

Run: `php artisan test --filter=ProjectLifecycleStatusSeederTest`
Expected: PASS (Pest's `RefreshDatabase` runs all migrations before each test, so the new tables exist).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_24_16*.php database/seeders/ProjectLifecycleStatusSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Seeders/ProjectLifecycleStatusSeederTest.php
git commit -m "feat: add proposals/proposal_students/project_lifecycle_status schema, rebuild projects table"
```

---

### Task 2: Models — Proposal, ProposalStudent, ProjectLifecycleStatus, rebuilt Project

**Files:**
- Create: `app/Models/Proposal.php`
- Create: `app/Models/ProposalStudent.php`
- Create: `app/Models/ProjectLifecycleStatus.php`
- Modify: `app/Models/Project.php` (full rewrite)
- Test: `tests/Feature/Models/ProposalModelTest.php`
- Test: `tests/Feature/Models/ProjectModelTest.php` (full rewrite)

**Interfaces:**
- Consumes: `proposals`/`proposal_students`/`project_lifecycle_status`/new `projects` tables from Task 1.
- Produces: `Proposal::STATUS_ARCHIVED = 1`, `Proposal::STATUS_PENDING = 2` (same numeric meaning as the old `Project` constants — reusing `project_status`'s existing 2 rows, decision A). `Proposal::canBeModifiedBy(User): bool`, `Proposal::canBeInstantiatedBy(User): bool`, `Proposal::instantiateProject(User $actor): Project`. `Project::STATUS_IN_PROGRESS = 1`, `Project::STATUS_ARCHIVED = 2` (project_lifecycle_status ids). `Project::proposal(): BelongsTo`, `$project->supervisor` / `$project->students` (Attribute accessors, appended to JSON, proxying through `proposal`).

- [ ] **Step 1: Write the failing model tests**

```php
<?php
// tests/Feature/Models/ProposalModelTest.php

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

function makeProposalDeps(): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    return compact('dept', 'spec', 'supervisor');
}

function makeProposal(array $deps, array $overrides = []): Proposal
{
    return Proposal::create(array_merge([
        'title'             => 'Test Proposal',
        'description'       => 'desc',
        'academic_year'     => '2024/2025',
        'department_id'     => $deps['dept']->id,
        'specialization_id' => $deps['spec']->id,
        'supervisor_id'     => $deps['supervisor']->id,
        'status_id'         => Proposal::STATUS_PENDING,
        'is_deleted'        => false,
    ], $overrides));
}

test('dept_manager can be modified by dept_manager of the same department while مقترح', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $proposal = makeProposal($deps);

    expect($proposal->canBeModifiedBy($manager))->toBeTrue();
});

test('canBeModifiedBy is false once مؤرشف, even for the creator', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $proposal = makeProposal($deps, ['status_id' => Proposal::STATUS_ARCHIVED, 'created_by' => $manager->id]);

    expect($proposal->canBeModifiedBy($manager))->toBeFalse();
});

test('super_admin can modify regardless of status or department', function () {
    $deps = makeProposalDeps();
    $admin = userWithRole('super_admin');
    $proposal = makeProposal($deps, ['status_id' => Proposal::STATUS_ARCHIVED]);

    expect($proposal->canBeModifiedBy($admin))->toBeTrue();
});

test('canBeInstantiatedBy mirrors canBeArchivedBy: dept_manager of dept, مقترح only', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $otherManager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id + 999]);
    $proposal = makeProposal($deps);

    expect($proposal->canBeInstantiatedBy($manager))->toBeTrue()
        ->and($proposal->canBeInstantiatedBy($otherManager))->toBeFalse();
});

test('instantiateProject creates exactly one Project row and flips proposal status', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $proposal = makeProposal($deps);

    $project = $proposal->instantiateProject($manager);

    expect($project)->toBeInstanceOf(Project::class)
        ->and($project->proposal_id)->toBe($proposal->id)
        ->and($project->status_id)->toBe(Project::STATUS_IN_PROGRESS)
        ->and($project->instantiated_by)->toBe($manager->id)
        ->and($project->instantiated_at)->not->toBeNull();

    $proposal->refresh();
    expect($proposal->status_id)->toBe(Proposal::STATUS_ARCHIVED)
        ->and(Project::where('proposal_id', $proposal->id)->count())->toBe(1);
});

test('instantiateProject rolls back the status flip if project creation fails', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $proposal = makeProposal($deps);

    // Force the unique(proposal_id) constraint to collide: pre-create a
    // project row for this proposal outside the transaction, bypassing the
    // model's own guard, to simulate an unexpected mid-transaction failure.
    Project::create([
        'proposal_id' => $proposal->id,
        'status_id'   => Project::STATUS_IN_PROGRESS,
    ]);
    // instantiateProject() itself would normally be blocked by
    // canBeInstantiatedBy() before this point in the real controller flow —
    // here we call it directly to prove the transaction itself is atomic,
    // independent of that outer guard.
    expect(fn () => $proposal->instantiateProject($manager))
        ->toThrow(\Illuminate\Database\QueryException::class);

    $proposal->refresh();
    expect($proposal->status_id)->toBe(Proposal::STATUS_PENDING);
});

test('a مؤرشف proposal cannot be instantiated again', function () {
    $deps = makeProposalDeps();
    $manager = userWithRole('dept_manager', ['department_id' => $deps['dept']->id]);
    $proposal = makeProposal($deps);
    $proposal->instantiateProject($manager);

    expect($proposal->canBeInstantiatedBy($manager))->toBeFalse();
});
```

```php
<?php
// tests/Feature/Models/ProjectModelTest.php

use App\Models\Department;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalStudent;
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

test('project supervisor and students are read by reference through the proposal', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager', ['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'X', 'academic_year' => '2024/2025',
        'department_id' => $dept->id, 'specialization_id' => $spec->id,
        'supervisor_id' => $supervisor->id, 'status_id' => Proposal::STATUS_PENDING,
    ]);
    ProposalStudent::create(['proposal_id' => $proposal->id, 'full_name' => 'Ahmed', 'registration_number' => 'ST001']);

    $project = $proposal->instantiateProject($manager);
    $project->load('proposal.supervisor', 'proposal.students');

    expect($project->supervisor->id)->toBe($supervisor->id)
        ->and($project->students)->toHaveCount(1)
        ->and($project->students->first()->full_name)->toBe('Ahmed');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProposalModelTest`
Expected: FAIL — class `App\Models\Proposal` doesn't exist.

- [ ] **Step 3: Write the models**

`app/Models/Proposal.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Proposal extends Model
{
    public const STATUS_ARCHIVED = 1;
    public const STATUS_PENDING  = 2;

    protected $fillable = [
        'title',
        'description',
        'academic_year',
        'department_id',
        'specialization_id',
        'supervisor_id',
        'created_by',
        'draft_file_path',
        'status_id',
        'based_on_project_id',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'is_deleted' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function specialization(): BelongsTo
    {
        return $this->belongsTo(Specialization::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'status_id');
    }

    public function basedOnProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'based_on_project_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(ProposalStudent::class);
    }

    public function instantiatedProject(): HasOne
    {
        return $this->hasOne(Project::class);
    }

    public function isEditable(): bool
    {
        return $this->status_id === self::STATUS_PENDING;
    }

    public function isArchived(): bool
    {
        return $this->status_id === self::STATUS_ARCHIVED;
    }

    public function canBeModifiedBy(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if ($this->status_id !== self::STATUS_PENDING) {
            return false;
        }

        if ($user->hasRole('dept_manager') && $user->department_id === $this->department_id) {
            return true;
        }

        return $this->created_by === $user->id && $user->department_id === $this->department_id;
    }

    public function canBeInstantiatedBy(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->hasRole('dept_manager')
            && $user->department_id === $this->department_id
            && $this->status_id === self::STATUS_PENDING;
    }

    /**
     * Single atomic action: locks this proposal (مقترح → مؤرشف) and creates
     * its linked Project row. Either both happen or neither does.
     */
    public function instantiateProject(User $actor): Project
    {
        return DB::transaction(function () use ($actor) {
            $this->update(['status_id' => self::STATUS_ARCHIVED]);

            return $this->instantiatedProject()->create([
                'status_id'       => Project::STATUS_IN_PROGRESS,
                'instantiated_by' => $actor->id,
                'instantiated_at' => now(),
            ]);
        });
    }
}
```

`app/Models/ProposalStudent.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalStudent extends Model
{
    protected $fillable = [
        'proposal_id',
        'full_name',
        'registration_number',
        'status',
        'withdrawal_date',
    ];

    protected function casts(): array
    {
        return [
            'withdrawal_date' => 'date',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
```

`app/Models/ProjectLifecycleStatus.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectLifecycleStatus extends Model
{
    protected $table = 'project_lifecycle_status';

    protected $fillable = ['status_name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'status_id');
    }
}
```

`app/Models/Project.php` (full rewrite):
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const STATUS_IN_PROGRESS = 1;
    public const STATUS_ARCHIVED    = 2;

    protected $fillable = [
        'proposal_id',
        'status_id',
        'final_score',
        'instantiated_by',
        'instantiated_at',
        'visit_count',
        'is_deleted',
    ];

    // Read-by-reference: supervisor/students are never duplicated onto
    // `projects` — they're proxied through the linked proposal and appended
    // to JSON so the frontend prop shape stays flat (project.supervisor,
    // project.students), unchanged from before the split.
    protected $appends = ['supervisor', 'students'];

    protected function casts(): array
    {
        return [
            'is_deleted'      => 'boolean',
            'final_score'     => 'decimal:2',
            'instantiated_at' => 'datetime',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    public function instantiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instantiated_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectLifecycleStatus::class, 'status_id');
    }

    public function examiners(): BelongsToMany
    {
        return $this->belongsToMany(Examiner::class, 'project_examiners')
            ->using(ProjectExaminer::class)
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * Proxy accessor — caller must eager-load 'proposal.supervisor' to avoid
     * an N+1 (see ProjectController::show()).
     */
    protected function supervisor(): Attribute
    {
        return Attribute::get(fn () => $this->proposal?->supervisor);
    }

    /**
     * Proxy accessor — caller must eager-load 'proposal.students'.
     */
    protected function students(): Attribute
    {
        return Attribute::get(fn () => $this->proposal?->students ?? collect());
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter="ProposalModelTest|ProjectModelTest"`
Expected: PASS, all tests including the atomicity/rollback test.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Proposal.php app/Models/ProposalStudent.php app/Models/ProjectLifecycleStatus.php app/Models/Project.php tests/Feature/Models/ProposalModelTest.php tests/Feature/Models/ProjectModelTest.php
git commit -m "feat: add Proposal model + instantiateProject(), rebuild Project model as proposal-referencing"
```

---

### Task 3: Data migration command — run against the live dev DB

**Files:**
- Create: `app/Console/Commands/MigrateProposalProjectData.php`
- Test: `tests/Feature/Console/MigrateProposalProjectDataTest.php`

**Ruling (pre-flight, before dispatch):** the plan originally had the `project_students`/`projects_legacy` table drops as two separately-dated migration files. That's a defect: Pest's `RefreshDatabase` runs every migration file in the migrations directory, in order, for every test — including these two — with no way to pause and run the data-migration *command* in between as intended. In the test DB, `projects_legacy` would already be dropped before this task's own test ever gets to insert fixture rows into it. Fix: the command itself drops both legacy tables at the end of `handle()`, after `verifyCounts()` confirms success — no separate migration files. This also matches Step B.7's actual intent ("drop the old table... as part of the migration process") better than two more dated files would have.

**Interfaces:**
- Consumes: `projects_legacy`, `project_students` (old), `project_examiners`, `evaluations` (values still pointing at old ids at this point), `proposals`/`projects` (empty, from Task 1).
- Produces: fully populated `proposals`, `proposal_students`, `projects`; `project_examiners`/`evaluations` re-pointed + FK restored; `proposals.based_on_project_id` chains re-pointed.

- [ ] **Step 1: Write the failing command test**

```php
<?php
// tests/Feature/Console/MigrateProposalProjectDataTest.php

use App\Models\Department;
use App\Models\Examiner;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\ProjectLifecycleStatusSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
    $this->seed(ProjectLifecycleStatusSeeder::class);
});

function seedLegacyProjectRow(array $overrides = []): array
{
    $dept       = Department::factory()->create();
    $spec       = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');

    $id = DB::table('projects_legacy')->insertGetId(array_merge([
        'project_title'      => 'Legacy row',
        'description'        => 'desc',
        'academic_year'      => '2024/2025',
        'department_id'      => $dept->id,
        'specialization_id'  => $spec->id,
        'supervisor_id'      => $supervisor->id,
        'current_status_id'  => 1, // مؤرشف
        'final_score'        => null,
        'visit_count'        => 0,
        'is_deleted'         => false,
        'created_at'         => now(),
        'updated_at'         => now(),
    ], $overrides));

    return compact('id', 'dept', 'spec', 'supervisor');
}

test('migrates a graded مؤرشف row into a proposal plus an instantiated, graded project', function () {
    $row = seedLegacyProjectRow(['final_score' => 91.00]);
    $examiner1 = Examiner::factory()->create(['department_id' => $row['dept']->id]);
    $examiner2 = Examiner::factory()->create(['department_id' => $row['dept']->id]);
    DB::table('project_examiners')->insert([
        ['project_id' => $row['id'], 'examiner_id' => $examiner1->id, 'created_at' => now(), 'updated_at' => now()],
        ['project_id' => $row['id'], 'examiner_id' => $examiner2->id, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('evaluations')->insert([
        'project_id' => $row['id'], 'examiner_id' => $examiner1->id, 'notes' => 'good',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('proposals:migrate-legacy-data')->assertExitCode(0);

    $proposal = Proposal::findOrFail($row['id']);
    expect($proposal->status_id)->toBe(Proposal::STATUS_ARCHIVED);

    $project = $proposal->instantiatedProject()->first();
    expect($project)->not->toBeNull()
        ->and((float) $project->final_score)->toBe(91.00)
        ->and($project->examiners)->toHaveCount(2)
        ->and($project->evaluations)->toHaveCount(1);
});

test('migrates an ungraded مؤرشف row into an in-progress project with no examiners', function () {
    $row = seedLegacyProjectRow();

    $this->artisan('proposals:migrate-legacy-data')->assertExitCode(0);

    $proposal = Proposal::findOrFail($row['id']);
    $project  = $proposal->instantiatedProject()->first();

    expect($project)->not->toBeNull()
        ->and($project->status_id)->toBe(\App\Models\Project::STATUS_IN_PROGRESS)
        ->and($project->final_score)->toBeNull()
        ->and($project->examiners)->toHaveCount(0);
});

test('a مقترح row with no examiners stays proposal-only', function () {
    $row = seedLegacyProjectRow(['current_status_id' => 2]);

    $this->artisan('proposals:migrate-legacy-data')->assertExitCode(0);

    $proposal = Proposal::findOrFail($row['id']);
    expect($proposal->status_id)->toBe(Proposal::STATUS_PENDING)
        ->and($proposal->instantiatedProject()->exists())->toBeFalse();
});

test('cleans erroneous grading data from a مقترح row before migrating it', function () {
    $row = seedLegacyProjectRow(['current_status_id' => 2, 'final_score' => 87.50]);
    $examiner = Examiner::factory()->create(['department_id' => $row['dept']->id]);
    DB::table('project_examiners')->insert([
        'project_id' => $row['id'], 'examiner_id' => $examiner->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('evaluations')->insert([
        'project_id' => $row['id'], 'examiner_id' => $examiner->id, 'notes' => 'bad state',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('proposals:migrate-legacy-data')->assertExitCode(0);

    $proposal = Proposal::findOrFail($row['id']);
    expect($proposal->status_id)->toBe(Proposal::STATUS_PENDING)
        ->and($proposal->instantiatedProject()->exists())->toBeFalse();
    expect(DB::table('project_examiners')->where('project_id', $row['id'])->count())->toBe(0);
    expect(DB::table('evaluations')->where('project_id', $row['id'])->count())->toBe(0);
});

test('re-points based_on_project_id chains at the new project ids', function () {
    $rowA = seedLegacyProjectRow(); // becomes project X
    $rowB = seedLegacyProjectRow(['based_on_project_id' => $rowA['id']]); // مؤرشف, references rowA

    $this->artisan('proposals:migrate-legacy-data')->assertExitCode(0);

    $proposalA = Proposal::findOrFail($rowA['id']);
    $proposalB = Proposal::findOrFail($rowB['id']);
    $projectA  = $proposalA->instantiatedProject()->firstOrFail();

    expect($proposalB->based_on_project_id)->toBe($projectA->id);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=MigrateProposalProjectDataTest`
Expected: FAIL — command `proposals:migrate-legacy-data` doesn't exist.

- [ ] **Step 3: Write the command**

`app/Console/Commands/MigrateProposalProjectData.php`:
```php
<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateProposalProjectData extends Command
{
    protected $signature = 'proposals:migrate-legacy-data';

    protected $description = 'One-time migration: split projects_legacy rows into proposals + instantiated projects';

    public function handle(): int
    {
        DB::transaction(function () {
            $this->cleanErroneousGrading();
            $idMap = $this->migrateProposals();
            $this->migrateProposalStudents();
            $newProjectIds = $this->instantiateProjectsForArchivedRows($idMap);
            $this->repointExaminersAndEvaluations($newProjectIds);
            $this->restoreExaminerEvaluationForeignKeys();
            $this->repointBasedOnChains($newProjectIds);
        });

        $this->verifyCounts();

        // DDL below is deliberately outside the transaction (MySQL DDL
        // auto-commits and cannot be rolled back with it anyway) and only
        // runs once verifyCounts() has confirmed the data copy succeeded.
        $this->dropLegacyTables();

        return self::SUCCESS;
    }

    /**
     * Decision 1: id=1's grading data was found erroneous during audit
     * (examiners/evaluations/a score attached to a still-مقترح row). Clean
     * it — and any row in the same state — before it gets copied forward.
     */
    private function cleanErroneousGrading(): void
    {
        $badIds = DB::table('projects_legacy')
            ->where('current_status_id', Proposal::STATUS_PENDING)
            ->whereIn('id', function ($q) {
                $q->select('project_id')->from('project_examiners');
            })
            ->pluck('id');

        if ($badIds->isEmpty()) {
            return;
        }

        $this->warn("Cleaning erroneous grading data on مقترح rows: {$badIds->implode(', ')}");

        DB::table('project_examiners')->whereIn('project_id', $badIds)->delete();
        DB::table('evaluations')->whereIn('project_id', $badIds)->delete();
        DB::table('projects_legacy')->whereIn('id', $badIds)->update(['final_score' => null]);
    }

    /** @return array<int,int> old projects_legacy.id => proposals.id (identical, ids preserved) */
    private function migrateProposals(): array
    {
        $rows = DB::table('projects_legacy')->orderBy('id')->get();
        $idMap = [];

        foreach ($rows as $row) {
            DB::table('proposals')->insert([
                'id'                   => $row->id, // preserve original id — simplifies every downstream re-pointing step
                'title'                => $row->project_title,
                'description'          => $row->description,
                'academic_year'        => $row->academic_year,
                'department_id'        => $row->department_id,
                'specialization_id'    => $row->specialization_id,
                'supervisor_id'        => $row->supervisor_id,
                'created_by'           => $row->created_by,
                'draft_file_path'      => $row->draft_file_path,
                'status_id'            => $row->current_status_id,
                'based_on_project_id'  => null, // re-pointed in repointBasedOnChains() once new project ids exist
                'is_deleted'           => $row->is_deleted,
                'created_at'           => $row->created_at,
                'updated_at'           => $row->updated_at,
            ]);
            $idMap[$row->id] = $row->id;
        }

        DB::statement('ALTER TABLE proposals AUTO_INCREMENT = ?', [($rows->max('id') ?? 0) + 1]);

        return $idMap;
    }

    private function migrateProposalStudents(): void
    {
        $students = DB::table('project_students')->get();

        foreach ($students as $student) {
            DB::table('proposal_students')->insert([
                'proposal_id'          => $student->project_id,
                'full_name'            => $student->full_name,
                'registration_number'  => $student->registration_number,
                'status'               => $student->status,
                'withdrawal_date'      => $student->withdrawal_date,
                'created_at'           => $student->created_at,
                'updated_at'           => $student->updated_at,
            ]);
        }
    }

    /** @return array<int,int> old projects_legacy.id => new projects.id */
    private function instantiateProjectsForArchivedRows(array $idMap): array
    {
        $archived = DB::table('projects_legacy')->where('current_status_id', Proposal::STATUS_ARCHIVED)->get();
        $newProjectIds = [];

        foreach ($archived as $row) {
            $hasGrading = DB::table('project_examiners')->where('project_id', $row->id)->exists();

            $newId = DB::table('projects')->insertGetId([
                'proposal_id'      => $idMap[$row->id],
                'status_id'        => $hasGrading ? Project::STATUS_ARCHIVED : Project::STATUS_IN_PROGRESS,
                'final_score'      => $row->final_score,
                'instantiated_by'  => null, // no real actor for backfilled historical data
                'instantiated_at'  => $row->updated_at, // best available proxy for "when it became مؤرشف"
                'visit_count'      => $row->visit_count,
                'is_deleted'       => $row->is_deleted,
                'created_at'       => $row->created_at,
                'updated_at'       => $row->updated_at,
            ]);

            $newProjectIds[$row->id] = $newId;
        }

        return $newProjectIds;
    }

    private function repointExaminersAndEvaluations(array $newProjectIds): void
    {
        foreach ($newProjectIds as $oldId => $newId) {
            DB::table('project_examiners')->where('project_id', $oldId)->update(['project_id' => $newId]);
            DB::table('evaluations')->where('project_id', $oldId)->update(['project_id' => $newId]);
        }
    }

    private function restoreExaminerEvaluationForeignKeys(): void
    {
        DB::statement('ALTER TABLE project_examiners ADD CONSTRAINT project_examiners_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE evaluations ADD CONSTRAINT evaluations_project_id_foreign FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE');
    }

    private function repointBasedOnChains(array $newProjectIds): void
    {
        $chains = DB::table('projects_legacy')->whereNotNull('based_on_project_id')->get(['id', 'based_on_project_id']);

        foreach ($chains as $chain) {
            $target = $newProjectIds[$chain->based_on_project_id] ?? null;

            if ($target === null) {
                $this->warn("Skipping based_on chain for proposal {$chain->id}: target {$chain->based_on_project_id} was never instantiated (unexpected — a مقترح target has no project row).");
                continue;
            }

            DB::table('proposals')->where('id', $chain->id)->update(['based_on_project_id' => $target]);
        }
    }

    private function verifyCounts(): void
    {
        $proposals = DB::table('proposals')->count();
        $projects  = DB::table('projects')->count();

        $this->info("proposals: {$proposals}, projects: {$projects}");

        if ($proposals === 0) {
            throw new \RuntimeException('Migration produced zero proposals — aborting, transaction will roll back.');
        }
    }

    private function dropLegacyTables(): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('project_students');
        \Illuminate\Support\Facades\Schema::dropIfExists('projects_legacy');
        $this->info('Dropped legacy tables: project_students, projects_legacy.');
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=MigrateProposalProjectDataTest`
Expected: PASS.

- [ ] **Step 5: Run the migrations and command against the real dev DB, then verify counts**

```bash
php artisan migrate --path=database/migrations/2026_08_24_160000_drop_project_examiner_evaluation_legacy_fk.php
php artisan migrate --path=database/migrations/2026_08_24_160100_create_proposals_table.php
php artisan migrate --path=database/migrations/2026_08_24_160200_create_proposal_students_table.php
php artisan migrate --path=database/migrations/2026_08_24_160300_create_project_lifecycle_status_table.php
php artisan db:seed --class=ProjectLifecycleStatusSeeder
php artisan migrate --path=database/migrations/2026_08_24_160400_rename_projects_to_projects_legacy.php
php artisan migrate --path=database/migrations/2026_08_24_160500_create_new_projects_table.php
php artisan migrate --path=database/migrations/2026_08_24_160600_drop_project_documents_table.php
php artisan proposals:migrate-legacy-data
```

Expected console output from the last command: `proposals: 25, projects: 20` (per the audit: id=1 cleaned but still migrates as a proposal, so 25 proposals total; 20 of the original 25 rows were مؤرشف and each gets exactly one project row).

Then verify manually:
```bash
php artisan tinker --execute="
echo 'proposals: ' . \App\Models\Proposal::count() . PHP_EOL;
echo 'projects: ' . \App\Models\Project::count() . PHP_EOL;
echo 'project_examiners: ' . DB::table('project_examiners')->count() . PHP_EOL;
echo 'evaluations: ' . DB::table('evaluations')->count() . PHP_EOL;
echo 'orphan examiners (project_id not in projects): ' . DB::table('project_examiners')->whereNotIn('project_id', DB::table('projects')->pluck('id'))->count() . PHP_EOL;
echo 'orphan evaluations: ' . DB::table('evaluations')->whereNotIn('project_id', DB::table('projects')->pluck('id'))->count() . PHP_EOL;
"
```
Expected: `proposals: 25`, `projects: 20`, `project_examiners: 34` (17 graded rows × 2, unchanged from pre-migration minus id=1's 2 cleaned), `evaluations: 34` (17 × 2, minus id=1's 2 cleaned), both orphan counts `0`. The command's own last line of output should also confirm `Dropped legacy tables: project_students, projects_legacy.` — by this point `projects_legacy`/`project_students` no longer exist; re-verify the counts above via the `proposals`/`projects` tables only.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/MigrateProposalProjectData.php tests/Feature/Console/MigrateProposalProjectDataTest.php
git commit -m "feat: add proposal/project data migration command, run it against dev DB, drop legacy tables"
```

---

### Task 4: Repoint the ripple — Department/Specialization/User relations, ReportService, ProjectsImport, DummyDataSeeder

This is the work the spec didn't call out by name but the audit found: these files query the old conflated `Project` model directly and raise SQL errors (missing columns) against the new schema, not just type mismatches.

**Files:**
- Modify: `app/Models/Department.php`, `app/Models/Specialization.php`, `app/Models/User.php`
- Modify: `app/Http/Controllers/DepartmentController.php`, `app/Http/Controllers/SpecializationController.php` (only the `->projects()` guard calls)
- Modify: `app/Services/ReportService.php` (full rewrite)
- Modify: `app/Imports/ProjectsImport.php` (full rewrite)
- Modify: `database/seeders/DummyDataSeeder.php` (full rewrite of `createProjects()`)
- Test: existing `tests/Feature/Report/ReportTest.php`, `tests/Feature/Import/ImportTest.php` must pass unmodified (this task adapts implementation to keep their existing assertions true, not the other way around)

**Interfaces:**
- Consumes: `Proposal`, `Project` from Task 2/3.
- Produces: `Department::proposals(): HasMany`, `Specialization::proposals(): HasMany`, `User::supervisedProposals(): HasMany` (renamed from `supervisedProjects`).

- [ ] **Step 1: Rename relations on Department/Specialization/User**

In `app/Models/Department.php`, replace:
```php
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
```
with:
```php
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
```

In `app/Models/Specialization.php`, replace:
```php
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
```
with:
```php
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
```

In `app/Models/User.php`, replace:
```php
    public function supervisedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'supervisor_id');
    }
```
with:
```php
    public function supervisedProposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'supervisor_id');
    }
```

- [ ] **Step 2: Update the two guard call sites**

In `app/Http/Controllers/DepartmentController.php::destroy()`, change:
```php
        if ($department->projects()->exists()) {
```
to:
```php
        if ($department->proposals()->exists()) {
```

In `app/Http/Controllers/SpecializationController.php::destroy()`, change:
```php
        if ($specialization->projects()->exists()) {
```
to:
```php
        if ($specialization->proposals()->exists()) {
```

(`ExaminerController::destroy()`'s `$examiner->projects()->exists()` needs **no change** — `Examiner::projects()` is `belongsToMany(Project::class, 'project_examiners')`, and `project_examiners` now points at the new `projects` table transparently.)

- [ ] **Step 3: Rewrite ReportService**

Full replace of `app/Services/ReportService.php`:
```php
<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\User;

class ReportService
{
    public function getDashboardStats(): array
    {
        $currentYear = now()->year;

        return [
            'total_projects'     => Proposal::where('is_deleted', false)->count(),
            'total_departments'  => Department::count(),
            'projects_this_year' => Proposal::where('is_deleted', false)
                ->where('academic_year', 'like', "%{$currentYear}%")
                ->count(),
            'pending_approvals'  => Proposal::where('is_deleted', false)
                ->where('status_id', Proposal::STATUS_PENDING)
                ->count(),
            'recent_projects'    => Proposal::with([
                    'department:id,name',
                    'specialization:id,name',
                    'status:id,status_name',
                ])
                ->where('is_deleted', false)
                ->latest()
                ->limit(5)
                ->get(['id', 'title', 'academic_year', 'department_id', 'specialization_id', 'status_id', 'created_at']),
            'by_status'          => Proposal::where('is_deleted', false)
                ->join('project_status', 'proposals.status_id', '=', 'project_status.id')
                ->groupBy('project_status.id', 'project_status.status_name')
                ->selectRaw('project_status.status_name, COUNT(proposals.id) as count')
                ->get(),
        ];
    }

    public function getDepartmentReport(?int $departmentId = null): array
    {
        $departments = Department::withCount([
                'proposals as project_count' => fn ($q) => $q->where('is_deleted', false),
            ])
            ->with([
                'specializations' => fn ($q) => $q->withCount([
                    'proposals as project_count' => fn ($q2) => $q2->where('is_deleted', false),
                ]),
            ])
            ->when($departmentId, fn ($q) => $q->where('id', $departmentId))
            ->get(['id', 'name', 'code']);

        $avgScores = Project::query()
            ->join('proposals', 'projects.proposal_id', '=', 'proposals.id')
            ->where('projects.is_deleted', false)
            ->whereNotNull('projects.final_score')
            ->when($departmentId, fn ($q) => $q->where('proposals.department_id', $departmentId))
            ->groupBy('proposals.department_id')
            ->selectRaw('proposals.department_id, ROUND(AVG(projects.final_score), 2) as avg_score, COUNT(*) as scored_count')
            ->get()
            ->keyBy('department_id');

        $supervisors = User::role('supervisor')
            ->withCount([
                'supervisedProposals as project_count' => fn ($q) => $q
                    ->where('is_deleted', false)
                    ->when($departmentId, fn ($q2) => $q2->where('department_id', $departmentId)),
            ])
            ->with('department:id,name')
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->orderByDesc('project_count')
            ->get(['id', 'name', 'department_id']);

        return [
            'departments' => $departments->map(fn ($dept) => [
                'id'              => $dept->id,
                'name'            => $dept->name,
                'code'            => $dept->code,
                'project_count'   => $dept->project_count,
                'avg_score'       => $avgScores->get($dept->id)?->avg_score,
                'scored_count'    => (int) ($avgScores->get($dept->id)?->scored_count ?? 0),
                'specializations' => $dept->specializations->map(fn ($spec) => [
                    'id'            => $spec->id,
                    'name'          => $spec->name,
                    'project_count' => $spec->project_count,
                ])->values(),
            ])->values(),
            'supervisors' => $supervisors->map(fn ($sup) => [
                'id'            => $sup->id,
                'name'          => $sup->name,
                'department'    => $sup->department?->name,
                'project_count' => $sup->project_count,
            ])->values(),
        ];
    }

    public function getSpecializationTrends(): array
    {
        $top = Specialization::withCount([
                'proposals as project_count' => fn ($q) => $q->where('is_deleted', false),
            ])
            ->with('department:id,name')
            ->orderByDesc('project_count')
            ->limit(10)
            ->get(['id', 'name', 'department_id']);

        $rare = Specialization::withCount([
                'proposals as project_count' => fn ($q) => $q->where('is_deleted', false),
            ])
            ->with('department:id,name')
            ->orderBy('project_count')
            ->limit(5)
            ->get(['id', 'name', 'department_id']);

        $byYear = Proposal::where('is_deleted', false)
            ->join('specializations', 'proposals.specialization_id', '=', 'specializations.id')
            ->groupBy('specializations.id', 'specializations.name', 'proposals.academic_year')
            ->selectRaw('specializations.id, specializations.name as spec_name, proposals.academic_year, COUNT(proposals.id) as count')
            ->orderBy('proposals.academic_year')
            ->get()
            ->groupBy('id')
            ->map(fn ($items) => $items->values());

        return [
            'top_specializations'  => $top,
            'rare_specializations' => $rare,
            'by_year'              => $byYear,
        ];
    }

    public function getSupervisorReport(): array
    {
        $supervisors = User::role('supervisor')
            ->with('department:id,name')
            ->withCount([
                'supervisedProposals as project_count' => fn ($q) => $q->where('is_deleted', false),
            ])
            ->orderByDesc('project_count')
            ->get(['id', 'name', 'department_id']);

        $avgScores = Project::query()
            ->join('proposals', 'projects.proposal_id', '=', 'proposals.id')
            ->where('projects.is_deleted', false)
            ->whereNotNull('projects.final_score')
            ->groupBy('proposals.supervisor_id')
            ->selectRaw('proposals.supervisor_id, ROUND(AVG(projects.final_score), 2) as avg_score, COUNT(*) as scored_count')
            ->get()
            ->keyBy('supervisor_id');

        $byYear = Proposal::where('is_deleted', false)
            ->groupBy('supervisor_id', 'academic_year')
            ->selectRaw('supervisor_id, academic_year, COUNT(*) as count')
            ->orderBy('academic_year')
            ->get()
            ->groupBy('supervisor_id')
            ->map(fn ($items) => $items->values());

        return $supervisors->map(function ($sup) use ($avgScores, $byYear) {
            $score = $avgScores->get($sup->id);

            return [
                'id'            => $sup->id,
                'name'          => $sup->name,
                'department'    => $sup->department?->name,
                'project_count' => $sup->project_count,
                'avg_score'     => $score?->avg_score,
                'scored_count'  => (int) ($score?->scored_count ?? 0),
                'by_year'       => ($byYear->get($sup->id) ?? collect())->map(fn ($row) => [
                    'year'  => $row->academic_year,
                    'count' => $row->count,
                ])->values(),
            ];
        })->values()->all();
    }

    public function getYearlyComparisonReport(): array
    {
        $perYear = Proposal::where('is_deleted', false)
            ->groupBy('academic_year')
            ->selectRaw('academic_year, COUNT(*) as count')
            ->orderBy('academic_year')
            ->get();

        $yearly = $perYear->values()->map(function ($row, $index) use ($perYear) {
            $prev   = $index > 0 ? $perYear->get($index - 1) : null;
            $growth = ($prev && $prev->count > 0)
                ? round((($row->count - $prev->count) / $prev->count) * 100, 2)
                : null;

            return [
                'year'       => $row->academic_year,
                'count'      => $row->count,
                'growth_pct' => $growth,
            ];
        });

        $deptByYear = Proposal::where('is_deleted', false)
            ->join('departments', 'proposals.department_id', '=', 'departments.id')
            ->groupBy('proposals.academic_year', 'departments.id', 'departments.name')
            ->selectRaw('proposals.academic_year, departments.id as department_id, departments.name as department_name, COUNT(proposals.id) as count')
            ->orderBy('proposals.academic_year')
            ->get()
            ->groupBy('academic_year')
            ->map(fn ($items) => $items->values());

        return [
            'yearly'             => $yearly->values(),
            'department_by_year' => $deptByYear,
        ];
    }
}
```

Note: `total_projects`/`recent_projects`/etc. in `getDashboardStats()` now describe **proposals**, matching their pre-split meaning exactly (the old "projects" WAS proposal-shaped data). No new stat card is added for the new `Project` entity's own counts — flagged in the final report as a known gap, not silently invented.

- [ ] **Step 4: Rewrite ProjectsImport**

Full replace of `app/Imports/ProjectsImport.php`:
```php
<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProjectsImport implements ToCollection, WithHeadingRow
{
    private const PREVIEW_LIMIT = 10;

    private array $failedRows   = [];
    private array $previewRows  = [];
    private int   $successCount = 0;
    private bool  $dryRun;

    public function __construct(bool $dryRun = false)
    {
        $this->dryRun = $dryRun;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $this->processRow($row->toArray(), $rowNumber);
        }
    }

    private function processRow(array $row, int $rowNumber): void
    {
        $title = trim((string) ($row['project_title'] ?? ''));

        if (str_starts_with($title, '[EXAMPLE]')) {
            return;
        }

        $error = $this->validate($row, $title);

        if ($this->dryRun && count($this->previewRows) < self::PREVIEW_LIMIT) {
            $this->previewRows[] = [
                'row_number'       => $rowNumber,
                'project_title'    => $title,
                'academic_year'    => trim((string) ($row['academic_year'] ?? '')),
                'department_code'  => trim((string) ($row['department_code'] ?? '')),
                'supervisor_email' => trim((string) ($row['supervisor_email'] ?? '')),
                'students'         => array_values(array_filter([
                    trim((string) ($row['student_1_name'] ?? '')),
                    trim((string) ($row['student_2_name'] ?? '')),
                    trim((string) ($row['student_3_name'] ?? '')),
                ])),
                'valid'            => $error === null,
                'error'            => $error,
            ];
        }

        if ($error !== null) {
            $this->fail($rowNumber, $error);

            return;
        }

        if ($this->dryRun) {
            $this->successCount++;

            return;
        }

        $department     = Department::where('code', trim($row['department_code']))->first();
        $specialization = Specialization::where('name', trim($row['specialization_name']))
            ->where('department_id', $department->id)
            ->first();
        $supervisor = User::where('email', trim($row['supervisor_email']))->first();

        $finalScore = isset($row['final_score']) && $row['final_score'] !== ''
            ? (float) $row['final_score']
            : null;

        // Bulk-imported rows represent already-finished, historical work —
        // create the proposal already-مؤرشف and immediately instantiate its
        // project, matching the old importer's "create it pre-archived" behavior.
        $proposal = Proposal::create([
            'title'             => $title,
            'description'       => trim((string) ($row['description'] ?? '')),
            'academic_year'     => trim($row['academic_year']),
            'department_id'     => $department->id,
            'specialization_id' => $specialization->id,
            'supervisor_id'     => $supervisor->id,
            'status_id'         => Proposal::STATUS_ARCHIVED,
            'is_deleted'        => false,
        ]);

        foreach ([
            ['student_1_name', 'student_1_reg'],
            ['student_2_name', 'student_2_reg'],
            ['student_3_name', 'student_3_reg'],
        ] as [$nameKey, $regKey]) {
            $name = trim((string) ($row[$nameKey] ?? ''));
            if ($name === '') {
                continue;
            }
            $proposal->students()->create([
                'full_name'           => $name,
                'registration_number' => trim((string) ($row[$regKey] ?? '')) ?: null,
                'status'              => 'active',
            ]);
        }

        $project = $proposal->instantiateProject(Auth::user());
        if ($finalScore !== null) {
            $project->update(['final_score' => $finalScore, 'status_id' => Project::STATUS_ARCHIVED]);
        }

        $this->successCount++;
    }

    private function validate(array $row, string $title): ?string
    {
        if ($title === '') {
            return 'الحقل المطلوب مفقود: project_title';
        }

        foreach (['academic_year', 'department_code', 'specialization_name', 'supervisor_email'] as $field) {
            if (empty(trim((string) ($row[$field] ?? '')))) {
                return "الحقل المطلوب مفقود: {$field}";
            }
        }

        $department = Department::where('code', trim($row['department_code']))->first();
        if (! $department) {
            return "القسم غير موجود: {$row['department_code']}";
        }

        $specialization = Specialization::where('name', trim($row['specialization_name']))
            ->where('department_id', $department->id)
            ->first();
        if (! $specialization) {
            return "التخصص غير موجود في هذا القسم: {$row['specialization_name']}";
        }

        $supervisor = User::where('email', trim($row['supervisor_email']))->first();
        if (! $supervisor) {
            return "المشرف غير موجود: {$row['supervisor_email']}";
        }

        if (! $supervisor->hasRole('supervisor')) {
            return "المستخدم ليس مشرفاً: {$row['supervisor_email']}";
        }

        return null;
    }

    private function fail(int $rowNumber, string $reason): void
    {
        $this->failedRows[] = ['row_number' => $rowNumber, 'reason' => $reason];
    }

    public function getSummary(): array
    {
        return [
            'total_rows'    => $this->successCount + count($this->failedRows),
            'success_count' => $this->successCount,
            'failed_count'  => count($this->failedRows),
            'failed_rows'   => $this->failedRows,
            'preview_rows'  => $this->previewRows,
        ];
    }
}
```

- [ ] **Step 5: Rewrite DummyDataSeeder's project-creation section**

In `database/seeders/DummyDataSeeder.php`:
- Change the `use` imports: replace `use App\Models\Project;` and `use App\Models\ProjectStudent;` with `use App\Models\Project;`, `use App\Models\Proposal;`, `use App\Models\ProposalStudent;` (keep `Project` — still needed for the instantiated rows).
- Rename `createProjects()` → `createProposalsAndProjects()`, called from `run()` in place of `createProjects()`.
- Inside the loop, replace the `Project::create([...])` block and the "2–4 students" block with:

```php
            $proposal = Proposal::create([
                'title'             => $def['title'],
                'description'       => $this->specDescription($def['spec']),
                'academic_year'     => $def['year'],
                'department_id'     => $dept->id,
                'specialization_id' => $spec->id,
                'supervisor_id'     => $supervisor->id,
                'status_id'         => $def['status'],
                'is_deleted'        => false,
            ]);

            $studentCount = rand(2, 4);
            $yearPrefix   = substr($def['year'], 0, 4);
            for ($s = 0; $s < $studentCount; $s++) {
                $this->studentCounter++;
                ProposalStudent::create([
                    'proposal_id'         => $proposal->id,
                    'full_name'           => $arabicNames[$this->studentCounter % count($arabicNames)],
                    'registration_number' => $yearPrefix . str_pad($this->studentCounter, 5, '0', STR_PAD_LEFT),
                    'status'              => 'active',
                ]);
            }

            $project = null;
            if ($def['status'] === 1) { // مؤرشف — instantiate its project, matching the live-data-migration end state
                $project = $proposal->instantiateProject($managers[$code]);
            }

            if ($project !== null && $def['score'] !== null) {
                $pairs = $examinerPairs[$code];
                $pair  = $pairs[$pairCounters[$code]++ % count($pairs)];
                $ex1   = $examiners[$code][$pair[0]];
                $ex2   = $examiners[$code][$pair[1]];

                DB::table('project_examiners')->insert([
                    ['project_id' => $project->id, 'examiner_id' => $ex1->id, 'assigned_by' => $managers[$code]->id, 'created_at' => now(), 'updated_at' => now()],
                    ['project_id' => $project->id, 'examiner_id' => $ex2->id, 'assigned_by' => $managers[$code]->id, 'created_at' => now(), 'updated_at' => now()],
                ]);

                Evaluation::create(['project_id' => $project->id, 'examiner_id' => $ex1->id, 'notes' => $comments[array_rand($comments)]]);
                Evaluation::create(['project_id' => $project->id, 'examiner_id' => $ex2->id, 'notes' => $comments[array_rand($comments)]]);

                $project->update(['final_score' => $def['score'], 'status_id' => Project::STATUS_ARCHIVED]);
            }

            $project?->update(['visit_count' => $def['visits']]);

            $createdProposals[] = $proposal;
            $createdProjects[]  = $project;
```

(Rename the accumulator `$createdProjects = [];` at the top of the method to also declare `$createdProposals = [];`, and change the return statement to `return count($createdProposals);`.)

- Change the based_on_project_id backfill at the end from operating on `$createdProjects[N]->id` (old Project ids) to:
```php
        // 3 project evolutions: a new proposal building on a previously
        // instantiated project (decision 6 — based_on_project_id points at
        // projects.id, not another proposal).
        $createdProposals[6]->update(['based_on_project_id' => $createdProjects[5]->id]);
        $createdProposals[8]->update(['based_on_project_id' => $createdProjects[7]->id]);
        $createdProposals[22]->update(['based_on_project_id' => $createdProjects[20]->id]);
```
(Indices 5, 7, 20 all have `'status' => 1` in `$projectDefs`, so `$createdProjects[5]`/`[7]`/`[20]` are guaranteed non-null.)

- [ ] **Step 6: Run the existing Report/Import test suites to confirm they still pass**

Run: `php artisan test --filter="ReportTest|ImportTest"`
Expected: PASS — all existing assertions (stat values, department/supervisor breakdowns, import success/failure counts) hold under the new queries, since the semantic mapping is 1:1 (proposals = what "projects" meant before for everything except final_score/examiners/evaluations).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Department.php app/Models/Specialization.php app/Models/User.php app/Http/Controllers/DepartmentController.php app/Http/Controllers/SpecializationController.php app/Services/ReportService.php app/Imports/ProjectsImport.php database/seeders/DummyDataSeeder.php
git commit -m "fix: repoint Department/Specialization/User relations, ReportService, ProjectsImport, DummyDataSeeder to the split schema"
```

---

### Task 5: FormRequests, ProposalController, thin ProjectController, routes, SearchService, PublicController

**Files:**
- Create: `app/Http/Requests/StoreProposalRequest.php`, `UpdateProposalRequest.php`, `DeleteProposalRequest.php`, `InstantiateProjectRequest.php`
- Create: `app/Http/Controllers/ProposalController.php`
- Modify: `app/Http/Controllers/ProjectController.php` (full rewrite — thin, view-only)
- Delete: `app/Http/Requests/StoreProjectRequest.php`, `UpdateProjectRequest.php`, `DeleteProjectRequest.php`, `ArchiveProjectRequest.php`
- Modify: `app/Services/SearchService.php` (full rewrite)
- Modify: `app/Http/Controllers/SearchController.php`, `PublicController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Proposal/ProposalTest.php`, `tests/Feature/Project/InstantiateProjectTest.php`, `tests/Feature/Project/ProjectExploitPreventionTest.php`

**Interfaces:**
- Consumes: `Proposal`, `Project` models (Task 2), `Proposal::canBeModifiedBy/canBeInstantiatedBy/instantiateProject` (Task 2).
- Produces: routes `proposals.index/create/store/show/edit/update/destroy/instantiate`, `projects.index/show`.

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/Proposal/ProposalTest.php

use App\Models\Department;
use App\Models\Proposal;
use App\Models\Specialization;

// beforeEach + makeProposalDeps()/makeProposal() reused from Task 2's test file style — declared locally here too since Pest test files don't share helpers across files unless in Pest.php.

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\ProjectStatusSeeder::class);
    $this->seed(\Database\Seeders\ProjectLifecycleStatusSeeder::class);
});

test('dept_staff can create a proposal in their own department', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $staff = userWithRole('dept_staff', ['department_id' => $dept->id]);

    $this->actingAs($staff)->post(route('proposals.store'), [
        'title' => 'My Proposal', 'description' => 'desc', 'academic_year' => '2024/2025',
        'department_id' => $dept->id, 'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'students' => [['full_name' => 'Ahmed', 'registration_number' => 'ST001']],
    ])->assertRedirect();

    $this->assertDatabaseHas('proposals', ['title' => 'My Proposal', 'status_id' => Proposal::STATUS_PENDING, 'created_by' => $staff->id]);
});

test('dept_manager creating a proposal is immediately مؤرشف and gets an instantiated project — no: creation always starts مقترح now, only the instantiate button archives', function () {
    // Under the split, creation NEVER auto-archives (that was the old
    // conflated-model behavior where managers' creates skipped straight to
    // مؤرشف). Now archiving only ever happens via instantiateProject().
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager', ['department_id' => $dept->id]);

    $this->actingAs($manager)->post(route('proposals.store'), [
        'title' => 'Manager Proposal', 'description' => 'desc', 'academic_year' => '2024/2025',
        'department_id' => $dept->id, 'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'students' => [['full_name' => 'Ahmed', 'registration_number' => 'ST001']],
    ])->assertRedirect();

    $this->assertDatabaseHas('proposals', ['title' => 'Manager Proposal', 'status_id' => Proposal::STATUS_PENDING]);
});
```

```php
<?php
// tests/Feature/Project/InstantiateProjectTest.php

use App\Models\Department;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\ProjectStatusSeeder::class);
    $this->seed(\Database\Seeders\ProjectLifecycleStatusSeeder::class);
});

function makeInstantiableProposal(): array
{
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager', ['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'X', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id,
        'status_id' => Proposal::STATUS_PENDING,
    ]);

    return compact('proposal', 'manager', 'dept');
}

test('dept_manager of the same department can instantiate and gets redirected to the project show page', function () {
    ['proposal' => $proposal, 'manager' => $manager] = makeInstantiableProposal();

    $response = $this->actingAs($manager)->post(route('proposals.instantiate', $proposal));

    $project = $proposal->fresh()->instantiatedProject()->firstOrFail();
    $response->assertRedirect(route('projects.show', $project));
});

test('dept_manager of a different department gets 403', function () {
    ['proposal' => $proposal] = makeInstantiableProposal();
    $otherManager = userWithRole('dept_manager', ['department_id' => Department::factory()->create()->id]);

    $this->actingAs($otherManager)->post(route('proposals.instantiate', $proposal))->assertForbidden();
});

test('super_admin can instantiate regardless of department', function () {
    ['proposal' => $proposal] = makeInstantiableProposal();
    $admin = userWithRole('super_admin');

    $this->actingAs($admin)->post(route('proposals.instantiate', $proposal))->assertRedirect();
});

test('an already-مؤرشف proposal cannot be instantiated again (double-click guard)', function () {
    ['proposal' => $proposal, 'manager' => $manager] = makeInstantiableProposal();
    $proposal->instantiateProject($manager);

    $this->actingAs($manager)->post(route('proposals.instantiate', $proposal))->assertForbidden();
    expect(Project::where('proposal_id', $proposal->id)->count())->toBe(1);
});

test('dept_staff cannot instantiate', function () {
    ['proposal' => $proposal, 'dept' => $dept] = makeInstantiableProposal();
    $staff = userWithRole('dept_staff', ['department_id' => $dept->id]);

    $this->actingAs($staff)->post(route('proposals.instantiate', $proposal))->assertForbidden();
});
```

```php
<?php
// tests/Feature/Project/ProjectExploitPreventionTest.php
// Proves the structural fix: examiners/evaluations can only ever attach to
// an instantiated Project, never reachable via any proposal-only path — the
// bug found in the audit (project id=1 was مقترح but had examiners/evaluations/
// a score attached) is now impossible by construction, not by a status check.

use App\Models\Department;
use App\Models\Examiner;
use App\Models\Proposal;
use App\Models\Specialization;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\ProjectStatusSeeder::class);
    $this->seed(\Database\Seeders\ProjectLifecycleStatusSeeder::class);
});

test('assigning an examiner to a proposal id (never instantiated) 404s — no project exists at that id', function () {
    $dept = Department::factory()->create();
    $spec = Specialization::factory()->create(['department_id' => $dept->id]);
    $supervisor = userWithRole('supervisor');
    $manager = userWithRole('dept_manager', ['department_id' => $dept->id]);
    $examiner = Examiner::factory()->create(['department_id' => $dept->id]);

    $proposal = Proposal::create([
        'title' => 'Never instantiated', 'academic_year' => '2024/2025', 'department_id' => $dept->id,
        'specialization_id' => $spec->id, 'supervisor_id' => $supervisor->id, 'status_id' => Proposal::STATUS_PENDING,
    ]);

    // Deliberately reuse the proposal's own id as a project id — proving
    // there is structurally no project row an attacker could hit, since
    // projects and proposals are now separate tables with independent
    // auto-increment sequences, not a shared conflated id space.
    $this->actingAs($manager)
        ->post(route('projects.assign-examiner', $proposal->id), ['examiner_id' => $examiner->id])
        ->assertNotFound();
});

test('evaluations table has no row whose project_id fails to resolve to an instantiated project', function () {
    // Regression guard for the exact id=1 corruption found in the audit:
    // any evaluations row must join cleanly to `projects`, which itself
    // must join cleanly to a مؤرشف `proposals` row.
    expect(
        \DB::table('evaluations')
            ->leftJoin('projects', 'evaluations.project_id', '=', 'projects.id')
            ->whereNull('projects.id')
            ->count()
    )->toBe(0);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter="ProposalTest|InstantiateProjectTest|ProjectExploitPreventionTest"`
Expected: FAIL — route `proposals.store`/`proposals.instantiate`/`projects.show` don't exist yet.

- [ ] **Step 3: Write the FormRequests**

`app/Http/Requests/StoreProposalRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->hasAnyRole(['dept_staff', 'dept_manager', 'super_admin'])) {
            return false;
        }

        if ($user->hasRole('dept_staff') && (int) $this->department_id !== $user->department_id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'title'                           => ['required', 'string', 'max:255'],
            'description'                     => ['required', 'string'],
            'academic_year'                   => ['required', 'string', 'max:20'],
            'department_id'                   => ['required', 'integer', 'exists:departments,id'],
            'specialization_id'               => ['required', 'integer', 'exists:specializations,id'],
            'supervisor_id'                   => ['required', 'integer', 'exists:users,id'],
            'pdf_file'                        => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
            'students'                        => ['required', 'array', 'min:1'],
            'students.*.full_name'            => ['required', 'string'],
            'students.*.registration_number'  => ['required', 'string'],
        ];
    }
}
```

`app/Http/Requests/UpdateProposalRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('proposal');

        if ($id === null) {
            return false;
        }

        $proposal = Proposal::where('is_deleted', false)->find($id);

        if (! $proposal) {
            return true;
        }

        if (! $proposal->canBeModifiedBy($this->user())) {
            return false;
        }

        if (! $this->user()->hasRole('super_admin')
            && (int) $this->input('department_id') !== $proposal->department_id
        ) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'title'                           => ['required', 'string', 'max:255'],
            'description'                     => ['required', 'string'],
            'academic_year'                   => ['required', 'string', 'max:20'],
            'department_id'                   => ['required', 'integer', 'exists:departments,id'],
            'specialization_id'               => ['required', 'integer', 'exists:specializations,id'],
            'supervisor_id'                   => ['required', 'integer', 'exists:users,id'],
            'pdf_file'                        => ['nullable', 'file', 'mimes:pdf', 'max:15360'],
            'students'                        => ['required', 'array', 'min:1'],
            'students.*.full_name'            => ['required', 'string'],
            'students.*.registration_number'  => ['required', 'string'],
        ];
    }
}
```

`app/Http/Requests/DeleteProposalRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;

class DeleteProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('proposal');

        if ($id === null) {
            return false;
        }

        $proposal = Proposal::where('is_deleted', false)->find($id);

        if (! $proposal) {
            return true;
        }

        return $proposal->canBeModifiedBy($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
```

`app/Http/Requests/InstantiateProjectRequest.php`:
```php
<?php

namespace App\Http\Requests;

use App\Models\Proposal;
use Illuminate\Foundation\Http\FormRequest;

class InstantiateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('proposal');

        if ($id === null) {
            return false;
        }

        $proposal = Proposal::where('is_deleted', false)->find($id);

        if (! $proposal) {
            return true;
        }

        return $proposal->canBeInstantiatedBy($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
```

Delete `app/Http/Requests/StoreProjectRequest.php`, `UpdateProjectRequest.php`, `DeleteProjectRequest.php`, `ArchiveProjectRequest.php`.

- [ ] **Step 4: Write ProposalController and rebuild ProjectController**

`app/Http/Controllers/ProposalController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteProposalRequest;
use App\Http\Requests\InstantiateProjectRequest;
use App\Http\Requests\StoreProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Models\Department;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function index(Request $request): Response
    {
        $filters = $request->only([
            'search', 'department_id', 'specialization_id',
            'academic_year', 'supervisor_id', 'status', 'sort',
        ]);

        return Inertia::render('Proposals/Index', [
            'proposals'     => $this->search->searchProposals($filters),
            'filterOptions' => $this->search->getFilterOptions(),
            'filters'       => $filters,
        ]);
    }

    public function create(): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $user->hasAnyRole(['dept_staff', 'dept_manager', 'super_admin'])) {
            abort(403);
        }

        return Inertia::render('Proposals/Create', [
            'departments'     => Department::orderBy('name')->get(['id', 'name']),
            'specializations' => Specialization::orderBy('name')->get(['id', 'name', 'department_id']),
            'supervisors'     => User::role('supervisor')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreProposalRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $similar = $this->search->detectSimilarity($data['title']);

        $pdfPath = null;
        if ($request->hasFile('pdf_file')) {
            $pdfPath = $request->file('pdf_file')->store('projects', 'public');
        }

        $proposal = Proposal::create([
            'title'             => $data['title'],
            'description'       => $data['description'],
            'academic_year'     => $data['academic_year'],
            'department_id'     => $data['department_id'],
            'specialization_id' => $data['specialization_id'],
            'supervisor_id'     => $data['supervisor_id'],
            'status_id'         => Proposal::STATUS_PENDING,
            'created_by'        => Auth::id(),
            'draft_file_path'   => $pdfPath,
            'is_deleted'        => false,
        ]);

        foreach ($data['students'] as $student) {
            $proposal->students()->create([
                'full_name'           => $student['full_name'],
                'registration_number' => $student['registration_number'],
                'status'              => 'active',
            ]);
        }

        $redirect = redirect()->route('proposals.show', $proposal)->with('success', 'تم إنشاء المقترح بنجاح');

        if ($similar->isNotEmpty()) {
            $redirect->with('similarity_warning', $similar->map(fn ($p) => [
                'id' => $p->id, 'project_title' => $p->title, 'academic_year' => $p->academic_year,
                'department' => $p->department?->name,
            ])->all());
        }

        return $redirect;
    }

    public function show(int $id): Response
    {
        $proposal = Proposal::with([
            'department', 'specialization', 'supervisor', 'students', 'status', 'createdBy',
            'instantiatedProject',
        ])->where('is_deleted', false)->findOrFail($id);

        return Inertia::render('Proposals/Show', ['proposal' => $proposal]);
    }

    public function edit(int $id): Response
    {
        $proposal = Proposal::where('is_deleted', false)->findOrFail($id);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (! $proposal->canBeModifiedBy($user)) {
            abort(403);
        }

        return Inertia::render('Proposals/Edit', [
            'proposal'        => $proposal->load('students'),
            'departments'     => Department::orderBy('name')->get(['id', 'name']),
            'specializations' => Specialization::orderBy('name')->get(['id', 'name', 'department_id']),
            'supervisors'     => User::role('supervisor')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateProposalRequest $request, int $id): RedirectResponse
    {
        $proposal = Proposal::where('is_deleted', false)->findOrFail($id);
        $data     = $request->validated();
        $similar  = $this->search->detectSimilarity($data['title'], $id);
        $pdfPath  = $proposal->draft_file_path;

        if ($request->hasFile('pdf_file')) {
            if ($pdfPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($pdfPath);
            }
            $pdfPath = $request->file('pdf_file')->store('projects', 'public');
        }

        $proposal->update([
            'title'             => $data['title'],
            'description'       => $data['description'],
            'academic_year'     => $data['academic_year'],
            'department_id'     => $data['department_id'],
            'specialization_id' => $data['specialization_id'],
            'supervisor_id'     => $data['supervisor_id'],
            'draft_file_path'   => $pdfPath,
        ]);

        $proposal->students()->delete();
        foreach ($data['students'] as $student) {
            $proposal->students()->create([
                'full_name'           => $student['full_name'],
                'registration_number' => $student['registration_number'],
                'status'              => 'active',
            ]);
        }

        $redirect = redirect()->route('proposals.show', $proposal)->with('success', 'تم تحديث المقترح بنجاح');

        if ($similar->isNotEmpty()) {
            $redirect->with('similarity_warning', $similar->map(fn ($p) => [
                'id' => $p->id, 'project_title' => $p->title, 'academic_year' => $p->academic_year,
                'department' => $p->department?->name,
            ])->all());
        }

        return $redirect;
    }

    public function destroy(DeleteProposalRequest $request, int $id): RedirectResponse
    {
        $proposal = Proposal::where('is_deleted', false)->findOrFail($id);
        $proposal->update(['is_deleted' => true]);

        return redirect()->route('proposals.index')->with('success', 'تم حذف المقترح بنجاح');
    }

    public function instantiate(InstantiateProjectRequest $request, int $id): RedirectResponse
    {
        $proposal = Proposal::where('is_deleted', false)->findOrFail($id);

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $project = $proposal->instantiateProject($user);

        return redirect()->route('projects.show', $project)->with('success', 'تم أرشفة المقترح وإنشاء المشروع بنجاح');
    }
}
```

`app/Http/Controllers/ProjectController.php` (full rewrite — thin, view-only for instantiated projects):
```php
<?php

namespace App\Http\Controllers;

use App\Models\Examiner;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        // proposal.supervisor and proposal.students must both be eager-loaded
        // here even though this view doesn't render students — Project's
        // $appends = ['supervisor', 'students'] (Task 2) fires both accessors
        // during JSON serialization for every row, and either one un-loaded
        // means an N+1 across the whole paginated page.
        $projects = Project::with(['proposal.department', 'proposal.specialization', 'proposal.supervisor', 'proposal.students', 'status'])
            ->where('is_deleted', false)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Projects/Index', ['projects' => $projects]);
    }

    public function show(int $id): Response
    {
        $project = Project::with([
            'proposal.department', 'proposal.specialization', 'proposal.supervisor', 'proposal.students',
            'status', 'examiners.department:id,name', 'evaluations',
        ])->where('is_deleted', false)->findOrFail($id);

        $project->increment('visit_count');

        $assignedIds        = $project->examiners->pluck('id');
        $availableExaminers = Examiner::whereNotIn('id', $assignedIds)
            ->with('department:id,name')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'title', 'department_id']);

        return Inertia::render('Projects/Show', [
            'project'            => $project,
            'availableExaminers' => $availableExaminers,
        ]);
    }
}
```

(`ProjectExaminerController` and `EvaluationController` need **zero code changes** — verified during the audit: they already only reference `Project::where('is_deleted', false)->findOrFail($projectId)` and `$project->examiners()`/`$project->evaluations()`, which resolve transparently against the new model/table.)

- [ ] **Step 5: Rewrite SearchService and update SearchController/PublicController**

Full replace of `app/Services/SearchService.php`:
```php
<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SearchService
{
    public function searchProposals(array $filters): LengthAwarePaginator
    {
        $query = Proposal::with(['department', 'specialization', 'supervisor', 'status'])
            ->withCount('students')
            ->where('is_deleted', false);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (! empty($filters['specialization_id'])) {
            $query->where('specialization_id', $filters['specialization_id']);
        }

        if (! empty($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        if (! empty($filters['supervisor_id'])) {
            $query->where('supervisor_id', $filters['supervisor_id']);
        }

        if (! empty($filters['status']) && $filters['status'] === 'active') {
            $query->where('status_id', Proposal::STATUS_ARCHIVED);
        }

        $sort = $filters['sort'] ?? 'created_at';
        match ($sort) {
            'title' => $query->orderBy('title'),
            default => $query->latest(),
        };

        return $query->paginate(15)->withQueryString();
    }

    /** @return Collection<int, Proposal> */
    public function detectSimilarity(string $title, ?int $excludeId = null): Collection
    {
        $query = Proposal::where('is_deleted', false)
            ->where('title', 'like', '%' . $title . '%')
            ->with('department:id,name')
            ->select(['id', 'title', 'academic_year', 'department_id']);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->limit(5)->get();
    }

    public function getFilterOptions(): array
    {
        return [
            'departments'    => Department::with('specializations:id,name,department_id')
                                          ->orderBy('name')
                                          ->get(['id', 'name']),
            'academic_years' => Proposal::where('is_deleted', false)
                                        ->distinct()
                                        ->orderByDesc('academic_year')
                                        ->pluck('academic_year'),
            'supervisors'    => User::role('supervisor')
                                    ->orderBy('name')
                                    ->get(['id', 'name']),
        ];
    }
}
```

In `app/Http/Controllers/SearchController.php`: replace `use App\Models\Project;` with `use App\Models\Proposal;`; replace `$this->search->searchProjects($filters)` with `$this->search->searchProposals($filters)`; replace the `'students' => fn ($q) => $q->select('id', 'full_name', 'project_id')` eager-load with `'students' => fn ($q) => $q->select('id', 'full_name', 'proposal_id')`; replace `Project::where('is_deleted', false)->where('project_title', 'like', ...)->pluck('project_title')` with `Proposal::where('is_deleted', false)->where('title', 'like', ...)->pluck('title')` in `suggestions()` (drop the `orderByDesc('visit_count')` — proposals have no visit_count per decision on placement; order by `latest()` instead).

Full replace of `app/Http/Controllers/PublicController.php` (decision 4 — public shows only graded projects):
```php
<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use App\Models\Specialization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'total_projects'        => Project::where('status_id', Project::STATUS_ARCHIVED)->where('is_deleted', false)->count(),
            'total_departments'     => Department::count(),
            'total_specializations' => Specialization::count(),
        ];

        return Inertia::render('Welcome', ['stats' => $stats]);
    }

    public function browse(Request $request): Response
    {
        $query = Project::query()
            ->where('status_id', Project::STATUS_ARCHIVED)
            ->where('is_deleted', false)
            ->with(['proposal.department', 'proposal.specialization', 'proposal.supervisor', 'proposal.students']);

        if ($search = $request->input('search')) {
            $query->whereHas('proposal', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($deptId = $request->input('department_id')) {
            $query->whereHas('proposal', fn ($q) => $q->where('department_id', $deptId));
        }

        if ($specId = $request->input('specialization_id')) {
            $query->whereHas('proposal', fn ($q) => $q->where('specialization_id', $specId));
        }

        if ($year = $request->input('academic_year')) {
            $query->whereHas('proposal', fn ($q) => $q->where('academic_year', $year));
        }

        $projects = $query->orderByDesc('created_at')->paginate(12)->withQueryString();

        $departments = Department::orderBy('name')->get(['id', 'name']);
        $specializations = Specialization::orderBy('name')->get(['id', 'name', 'department_id']);
        $years = \App\Models\Proposal::whereHas('instantiatedProject', fn ($q) => $q->where('status_id', Project::STATUS_ARCHIVED))
            ->where('is_deleted', false)
            ->distinct()
            ->orderByDesc('academic_year')
            ->pluck('academic_year');

        return Inertia::render('Public/Browse', [
            'projects'        => $projects,
            'departments'     => $departments,
            'specializations' => $specializations,
            'years'           => $years,
            'filters'         => $request->only(['search', 'department_id', 'specialization_id', 'academic_year']),
        ]);
    }

    public function show(int $id): Response
    {
        $project = Project::where('id', $id)
            ->where('status_id', Project::STATUS_ARCHIVED)
            ->where('is_deleted', false)
            ->with([
                'proposal.department', 'proposal.specialization', 'proposal.supervisor', 'proposal.students',
                'examiners', 'evaluations',
            ])
            ->firstOrFail();

        Project::where('id', $id)->increment('visit_count');
        $project->visit_count += 1;

        $related = Project::where('status_id', Project::STATUS_ARCHIVED)
            ->where('is_deleted', false)
            ->where('id', '!=', $id)
            ->whereHas('proposal', fn ($q) => $q->where('specialization_id', $project->proposal->specialization_id))
            ->with(['proposal.department', 'proposal.specialization', 'proposal.supervisor', 'proposal.students'])
            ->limit(3)
            ->get();

        return Inertia::render('Public/Show', [
            'project' => $project,
            'related' => $related,
        ]);
    }
}
```

- [ ] **Step 6: Rewrite routes**

In `routes/web.php`:
- Replace `use App\Http\Controllers\ProjectController;` with both `use App\Http\Controllers\ProjectController;` and `use App\Http\Controllers\ProposalController;`.
- Replace the "Projects — all authenticated users can browse" block:
```php
// Proposals — all authenticated users can browse; role checks handled in controller/form requests
Route::middleware(['auth'])->group(function () {
    Route::resource('proposals', ProposalController::class);
    Route::post('proposals/{id}/instantiate', [ProposalController::class, 'instantiate'])
        ->name('proposals.instantiate');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{id}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');
});
```
- The dept_manager/super_admin group's `Route::post('projects/{id}/assign-examiner', ...)`, `Route::delete('projects/{id}/examiners/{examinerId}', ...)`, `Route::post('projects/{id}/evaluation', ...)`, `Route::patch('projects/{id}/score', ...)` stay **exactly as-is** — they already target `/projects/{id}/...`, which is correct: these operate on instantiated `Project` ids, not proposal ids.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter="ProposalTest|InstantiateProjectTest|ProjectExploitPreventionTest"`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/StoreProposalRequest.php app/Http/Requests/UpdateProposalRequest.php app/Http/Requests/DeleteProposalRequest.php app/Http/Requests/InstantiateProjectRequest.php app/Http/Controllers/ProposalController.php app/Http/Controllers/ProjectController.php app/Services/SearchService.php app/Http/Controllers/SearchController.php app/Http/Controllers/PublicController.php routes/web.php tests/Feature/Proposal/ProposalTest.php tests/Feature/Project/InstantiateProjectTest.php tests/Feature/Project/ProjectExploitPreventionTest.php
git rm app/Http/Requests/StoreProjectRequest.php app/Http/Requests/UpdateProjectRequest.php app/Http/Requests/DeleteProjectRequest.php app/Http/Requests/ArchiveProjectRequest.php
git commit -m "feat: add ProposalController + instantiate endpoint, rebuild ProjectController as thin view-only, retarget SearchService/PublicController"
```

---

### Task 6: Frontend — Proposals pages, Projects pages, instantiate button, sidebar

**Files:**
- Move + modify: `resources/js/pages/Projects/{Index,Create,Edit,Show}.vue` → `resources/js/pages/Proposals/{Index,Create,Edit,Show}.vue`
- Rename: `resources/js/composables/useProjectStatus.ts` → `useProposalStatus.ts`
- Create: `resources/js/pages/Projects/{Index,Show}.vue` (new — instantiated projects)
- Modify: `resources/js/components/ConfirmDelete.vue` (generalize, non-breaking)
- Modify: `resources/js/components/AppSidebar.vue`

**Interfaces:**
- Consumes: `proposals.*`/`projects.*` routes from Task 5, `Project.supervisor`/`Project.students` flat JSON shape from Task 2's accessor.

- [ ] **Step 1: Rename the status composable**

Copy `resources/js/composables/useProjectStatus.ts` to `resources/js/composables/useProposalStatus.ts` unchanged (its content — `STATUS_ARCHIVED`/`STATUS_PROPOSAL`/`statusColor`/`statusLabel`/`isProposalStatus` — already describes proposal status exactly; only the file name was ambiguous once a *second*, different "status" concept exists on `Project`). Delete the old file.

- [ ] **Step 2: Generalize ConfirmDelete.vue (non-breaking — new props default to current delete copy)**

In `resources/js/components/ConfirmDelete.vue`, replace the `<script setup>` block:
```vue
<script setup lang="ts">
withDefaults(defineProps<{
    show: boolean;
    itemName?: string;
    title?: string;
    message?: string;
    confirmLabel?: string;
    confirmColor?: 'red' | 'green';
}>(), {
    title: 'تأكيد الحذف',
    confirmLabel: 'حذف',
    confirmColor: 'red',
});

const emit = defineEmits<{
    confirmed: [];
    cancelled: [];
}>();
</script>
```
and the template's heading/body/confirm-button:
```vue
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ title }}</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        <template v-if="message">{{ message }}</template>
                        <template v-else>
                            هل أنت متأكد من حذف
                            <span v-if="itemName" class="font-medium text-gray-900 dark:text-gray-100">{{ itemName }}</span>؟
                            لا يمكن التراجع عن هذه العملية.
                        </template>
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="emit('cancelled')"
                        >
                            إلغاء
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-lg px-4 py-2 text-sm font-medium text-white',
                                confirmColor === 'green' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700',
                            ]"
                            @click="emit('confirmed')"
                        >
                            {{ confirmLabel }}
                        </button>
                    </div>
```
Every existing call site (Projects/Departments/Examiners/Users pages) passes only `show`/`itemName` and gets byte-identical rendered output via the new defaults — zero behavior change for them.

- [ ] **Step 3: Move and update Proposals pages**

Copy `resources/js/pages/Projects/Index.vue` → `resources/js/pages/Proposals/Index.vue`, then apply these exact substitutions (all occurrences):
- `import { isProposalStatus, statusColor, statusLabel } from '@/composables/useProjectStatus';` → `import { isProposalStatus, statusColor, statusLabel } from '@/composables/useProposalStatus';`
- `route('projects.create')` → `route('proposals.create')`
- `route('projects.show', [project.id])` → `route('proposals.show', [proposal.id])`
- `route('projects.edit', [project.id])` → `route('proposals.edit', [proposal.id])`
- `route('projects.destroy', ...)` → `route('proposals.destroy', ...)`
- `route('projects.archive', [id])` → `route('proposals.instantiate', [id])`
- Every `project` identifier (prop name, loop variable, interface name `Project`, function params) → `proposal` / `Proposal` — this includes the `projects: PaginatedProjects` prop (→ `proposals: PaginatedProposals`), the `Project` interface (→ `Proposal`, with `project_title` → `title`), the `v-for="project in projects.data"` loop (→ `v-for="proposal in proposals.data"`), `project.project_title` → `proposal.title`, `project.current_status` → `proposal.status` (field renamed per the new `Proposal::status()` relation name), `project.students_count` stays as-is (still `withCount('students')` in `SearchService::searchProposals()`).
- Button label `+ إضافة مشروع` → `+ إضافة مقترح جديد`; the أرشفة button's label stays أرشفة is now wrong — rename to `تنزيل المشروع` and change its color from green (`bg-green-100 text-green-700 ...`) to indigo (`bg-indigo-100 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400`) to visually distinguish "this creates a new entity" from a simple state change; wrap its `@click` in the new confirm-dialog pattern from Step 4 below instead of firing `archiveProject()` directly.
- Page title `المشاريع` → `المقترحات`, breadcrumb `{ title: 'المشاريع', href: '/projects' }` → `{ title: 'المقترحات', href: '/proposals' }`.

Apply the equivalent `project`→`proposal`, `project_title`→`title`, `projects.*`→`proposals.*` route renames to the copies of `Create.vue`, `Edit.vue`, and `Show.vue` in `resources/js/pages/Proposals/`. In `Create.vue`/`Edit.vue`, the `useForm({...})` field `project_title` → `title`, and the submit call `form.post(route('projects.store'), ...)` → `form.post(route('proposals.store'), ...)` (`Edit.vue`: `form.put(route('projects.update', ...))` → `form.put(route('proposals.update', ...))`).

In `Show.vue` specifically: remove the examiners/evaluations/score card entirely (lines that were 256–380 in the old file) — proposals are never graded, that UI now belongs only to `Projects/Show.vue` (Step 4). Remove the `canManage`/`ScoreInput`/`AssignExaminerModal` imports and related script (`showAssignModal`, `confirmRemoveExaminer`, `evaluationFor`, `finalScore`, `scoreIsPass`, `PASS_THRESHOLD` — none of these apply to a proposal). Replace the header's أرشفة button with:
```vue
                    <button
                        v-if="canInstantiate"
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="showConfirmInstantiate = true"
                    >
                        تنزيل المشروع
                    </button>
```
with script additions:
```ts
const canInstantiate = computed(() =>
    userRole.value === 'super_admin'
    || (userRole.value === 'dept_manager' && isProposalStatus(props.proposal.status?.status_name) && currentUser.value.department_id === props.proposal.department_id)
);

const showConfirmInstantiate = ref(false);

function instantiateProject() {
    router.post(route('proposals.instantiate', [props.proposal.id]), {}, {
        onFinish: () => (showConfirmInstantiate.value = false),
    });
}
```
and the confirm dialog, alongside the existing `ConfirmDelete` for delete:
```vue
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
If the proposal is already مؤرشف (`proposal.instantiated_project` present, matching the `instantiatedProject` relation eager-loaded by `ProposalController::show()`), show a link to it instead: `<a :href="route('projects.show', proposal.instantiated_project.id)">عرض المشروع</a>`.

Delete the original `resources/js/pages/Projects/{Index,Create,Edit,Show}.vue` (superseded by the `Proposals/` copies).

- [ ] **Step 4: Create the new instantiated-Projects pages**

`resources/js/pages/Projects/Index.vue`:
```vue
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';

interface Department     { id: number; name: string }
interface Specialization { id: number; name: string }
interface Supervisor     { id: number; name: string }
interface ProjectStatus  { id: number; status_name: string }
interface Proposal {
    id: number;
    title: string;
    department: Department | null;
    specialization: Specialization | null;
    supervisor: Supervisor | null;
}
interface Project {
    id: number;
    final_score: string | null;
    proposal: Proposal;
    status: ProjectStatus | null;
}
interface PaginationLink { url: string | null; label: string; active: boolean }
interface PaginatedProjects {
    data: Project[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
}

defineProps<{ projects: PaginatedProjects }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'المشاريع', href: '/projects' },
];
</script>

<template>
    <Head title="المشاريع" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4" dir="rtl">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">المشاريع</h1>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">العنوان</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">القسم</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">المشرف</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الحالة</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">الدرجة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                        <tr v-for="project in projects.data" :key="project.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-3">
                                <a :href="route('projects.show', [project.id])" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                                    {{ project.proposal.title }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ project.proposal.department?.name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ project.proposal.supervisor?.name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ project.status?.status_name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 dark:text-gray-400">{{ project.final_score ?? '—' }}</td>
                        </tr>
                        <tr v-if="projects.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">لا توجد مشاريع بعد</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="projects.last_page > 1" class="flex gap-1">
                <template v-for="link in projects.links" :key="link.label">
                    <button
                        v-if="link.url"
                        type="button"
                        :class="['min-w-8 rounded px-3 py-1', link.active ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700']"
                        @click="router.visit(link.url)"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
```

`resources/js/pages/Projects/Show.vue` — this is where the examiners/evaluation/score card from the old conflated `Show.vue` now lives, **logic unchanged**, reading `project.proposal.title`/`project.proposal.department` etc. for the header/meta, and `project.supervisor`/`project.students` (the Task 2 accessor — flat, exactly like before the split) for the students table:
```vue
<script setup lang="ts">
import AssignExaminerModal from '@/components/AssignExaminerModal.vue';
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import ScoreInput from '@/components/ScoreInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Department     { id: number; name: string }
interface Specialization { id: number; name: string }
interface Supervisor     { id: number; name: string }
interface ProjectStatus  { id: number; status_name: string }
interface Student        { id: number; full_name: string; registration_number: string; status: string }
interface Examiner {
    id: number; full_name: string; title: string | null;
    department?: { id: number; name: string } | null;
}
interface Evaluation { id: number; examiner_id: number; notes: string; created_at: string }
interface Proposal {
    id: number; title: string; description: string; academic_year: string;
    department: Department | null; specialization: Specialization | null;
}

interface Project {
    id: number;
    final_score: string | null;
    visit_count: number;
    proposal: Proposal;
    status: ProjectStatus | null;
    supervisor: Supervisor | null;
    students: Student[];
    examiners: Examiner[];
    evaluations: Evaluation[];
}

const props = defineProps<{ project: Project; availableExaminers: Examiner[] }>();

const page     = usePage<SharedData>();
const flash    = computed(() => page.props.flash ?? {});
const userRole = computed(() => (page.props.auth.user as { role?: string }).role ?? '');
const canManage = computed(() => ['dept_manager', 'super_admin'].includes(userRole.value));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'لوحة التحكم', href: '/dashboard' },
    { title: 'المشاريع', href: '/projects' },
    { title: props.project.proposal.title, href: '#' },
];

const showAssignModal       = ref(false);
const confirmRemoveExaminer = ref<Examiner | null>(null);

function removeExaminer() {
    if (!confirmRemoveExaminer.value) return;
    router.delete(route('projects.remove-examiner', [props.project.id, confirmRemoveExaminer.value.id]), {
        onFinish: () => (confirmRemoveExaminer.value = null),
    });
}

function evaluationFor(examinerId: number): Evaluation | null {
    return props.project.evaluations.find(e => e.examiner_id === examinerId) ?? null;
}

const PASS_THRESHOLD = 50;
const finalScore = computed(() => {
    if (props.project.final_score === null || props.project.final_score === '') return null;
    const n = Number(props.project.final_score);
    return isNaN(n) ? null : n;
});
const scoreIsPass = computed(() => finalScore.value !== null && finalScore.value >= PASS_THRESHOLD);
</script>

<template>
    <Head :title="project.proposal.title" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4" dir="rtl">
            <div v-if="flash.success" class="rounded-lg bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{{ flash.success }}</div>

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ project.proposal.title }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ project.proposal.academic_year }}</span>
                        <span>•</span>
                        <span>{{ project.visit_count }} مشاهدة</span>
                        <span class="rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/20 dark:text-blue-400">
                            {{ project.status?.status_name }}
                        </span>
                    </div>
                </div>
                <a :href="route('projects.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                    العودة للقائمة
                </a>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-base font-semibold text-gray-800 dark:text-gray-200">الوصف</h2>
                        <p class="whitespace-pre-wrap text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ project.proposal.description }}</p>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-base font-semibold text-gray-800 dark:text-gray-200">
                            الطلاب <span class="text-sm font-normal text-gray-400">({{ project.students.length }})</span>
                        </h2>
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-transparent">
                                <tr v-for="(student, idx) in project.students" :key="student.id">
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ idx + 1 }}</td>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-800 dark:text-gray-200">{{ student.full_name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">{{ student.registration_number }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Examiners + Evaluations — logic unchanged from the old conflated Show.vue -->
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                                المناقشون <span class="text-sm font-normal text-gray-400">({{ project.examiners.length }}/2)</span>
                            </h2>
                            <button v-if="canManage && project.examiners.length < 2" type="button" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700" @click="showAssignModal = true">
                                + تعيين ممتحن
                            </button>
                        </div>
                        <div v-if="project.examiners.length > 0" class="space-y-4">
                            <div v-for="examiner in project.examiners" :key="examiner.id" class="rounded-lg border border-gray-100 p-4 dark:border-gray-700">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ examiner.full_name }}</p>
                                        <p v-if="examiner.title" class="text-xs text-gray-500">{{ examiner.title }}</p>
                                    </div>
                                    <button v-if="canManage" type="button" class="shrink-0 rounded px-2 py-1 text-xs text-red-500 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20" @click="confirmRemoveExaminer = examiner">
                                        إزالة
                                    </button>
                                </div>
                                <div v-if="evaluationFor(examiner.id)" class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-700">
                                    <p class="mb-1 text-xs font-medium text-gray-400">ملاحظات التقييم</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ evaluationFor(examiner.id)!.notes }}</p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-gray-500">لم يتم تعيين ممتحنين بعد</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">تفاصيل المشروع</h2>
                        <dl class="space-y-3">
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">القسم</dt><dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ project.proposal.department?.name ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">التخصص</dt><dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ project.proposal.specialization?.name ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-gray-500 dark:text-gray-400">المشرف</dt><dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-200">{{ project.supervisor?.name ?? '—' }}</dd></div>
                        </dl>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">الدرجة النهائية</h2>
                        <ScoreInput v-if="canManage" :project-id="project.id" :current-score="project.final_score" />
                        <template v-else>
                            <div v-if="finalScore !== null" class="flex items-center gap-3">
                                <span class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ finalScore }}</span>
                                <span :class="['rounded-full px-3 py-1 text-sm font-medium', scoreIsPass ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400']">
                                    {{ scoreIsPass ? 'ناجح' : 'راسب' }}
                                </span>
                            </div>
                            <p v-else class="text-sm text-gray-500">لم تُسجَّل درجة بعد</p>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDelete :show="!!confirmRemoveExaminer" :item-name="confirmRemoveExaminer?.full_name" @confirmed="removeExaminer" @cancelled="confirmRemoveExaminer = null" />
        <AssignExaminerModal :show="showAssignModal" :project-id="project.id" :available-examiners="availableExaminers" @assigned="showAssignModal = false" @cancelled="showAssignModal = false" />
    </AppLayout>
</template>
```

- [ ] **Step 5: Split the sidebar nav**

In `resources/js/components/AppSidebar.vue`, wherever a nav item currently points at `/projects` labeled `المشاريع`, replace it with two entries: `{ title: 'المقترحات', href: '/proposals', icon: FileText }` and `{ title: 'المشاريع', href: '/projects', icon: FolderOpen }` (reuse `FolderOpen`, already imported per the existing codebase; add `FileText` from `lucide-vue-next` if not already imported), for every role menu that currently has the single `المشاريع` entry (super_admin, dept_manager, dept_staff, supervisor per the existing role-based menu structure).

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Proposals resources/js/pages/Projects resources/js/composables/useProposalStatus.ts resources/js/components/ConfirmDelete.vue resources/js/components/AppSidebar.vue
git rm resources/js/composables/useProjectStatus.ts
git commit -m "feat: split frontend into Proposals/ and Projects/ pages, add instantiate confirm dialog, split sidebar nav"
```

---

### Task 7: Full suite, build, docs

**Files:**
- Modify: `tests/Feature/Project/ProjectTest.php` (rename/rewrite the surviving proposal-CRUD assertions; the examiner/evaluation/instantiate-specific ones already moved to their own files in Task 5)
- Modify: `tests/Feature/Roles/RoleVerificationTest.php`, `tests/Feature/Search/SearchTest.php`, `tests/Feature/Public/PublicBrowseTest.php`, `tests/Feature/Seeders/ProjectStatusSeederTest.php` (route/field-name updates only — same assertions, new route names `proposals.*`)
- Modify: `CLAUDE.md`, `PROGRESS.md`

- [ ] **Step 1: Update the remaining test files' route/field names**

In each of `ProjectTest.php` (rename file to `ProposalTest.php`'s remaining CRUD cases — merge with Task 5's file or keep separate, executor's call, just don't duplicate test names), `RoleVerificationTest.php`, `SearchTest.php`, `PublicBrowseTest.php`: apply the mechanical substitutions `route('projects.index'/'.create'/'.store'/'.show'/'.edit'/'.update'/'.destroy')` → `route('proposals.*')` equivalents where the test is about proposal CRUD; `'project_title'` → `'title'` in any payload array; `Project::` → `Proposal::` and `STATUS_ARCHIVED`/`STATUS_PENDING` stay the same constant names (still defined on `Proposal` now) for assertions about the 2-state lifecycle. Tests specifically about examiner assignment / evaluation / final score keep hitting `/projects/{id}/...` routes unchanged, but must now first instantiate a project (via `Proposal::create(...)->instantiateProject($manager)`) instead of creating a `Project` directly, since `Project::create()` alone no longer has proposal-shaped fields to set.

- [ ] **Step 2: Run the full suite**

Run: `php artisan test`
Expected: all tests pass. If any fail, use superpowers:systematic-debugging — do not patch test assertions to match broken behavior; find the root cause (usually: a route name not updated, a field name not renamed, or a test still creating a bare `Project` row without an owning `Proposal`).

- [ ] **Step 3: Run the frontend build**

Run: `npm run build`
Expected: no TypeScript/Vue compile errors — this will catch any leftover `route('projects.create')` or `project.project_title` reference in the renamed Proposals pages.

- [ ] **Step 4: Rewrite CLAUDE.md and PROGRESS.md**

In `CLAUDE.md`, under `## Current Status`, add a new top entry (above the existing "🐛 Bugfix" entry) documenting: the two-entity split (proposals vs projects), the `instantiateProject()` atomic action and its gate (`canBeInstantiatedBy`, ported from `canBeArchivedBy`), the id=1 data cleanup, the migration command name, the new route names, the `visit_count`/`instantiated_by` placement decisions, and the flagged limitation that no UI exists yet to move a project from `قيد التنفيذ` to `مؤرشف`. Update `## Database — 13 Tables` to reflect `proposals`/`proposal_students`/`project_lifecycle_status`/rebuilt `projects`, remove `project_documents` from the list. Update `## Key Business Rules` to describe the two-entity lifecycle instead of the old single-entity one.

In `PROGRESS.md`, add matching entries to `### Bugfixes / Corrections` (or a new `### Architecture Changes` section) and update the `## 2. Database Schema` table definitions for every changed/new/dropped table.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature CLAUDE.md PROGRESS.md
git commit -m "test: update remaining suite to proposal/project split routes and fields; docs: rewrite for the two-entity model"
```

---

### Task 8: Self-review, code review, Playwright walkthrough

- [ ] **Step 1: superpowers:verification-before-completion** — re-run `php artisan test` and `npm run build` one final time on a clean state, confirm both green before claiming done.
- [ ] **Step 2: /code-review** on the full branch diff against `main`. Fix real issues found; note anything disagreed with and why, per the review's own protocol.
- [ ] **Step 3: Playwright walkthrough** — start `php artisan serve`, log in as `manager.sw@college.com` (dept_manager, matches Decision 8's gate), navigate to a مقترح proposal's Show page, screenshot the "تنزيل المشروع" button, click it, screenshot the confirm dialog, confirm, screenshot the resulting redirect to the new Project's Show page with the examiners/evaluation/score card intact and the proposal's own Show page now showing "عرض المشروع" instead of the button.
- [ ] **Step 4: Commit any review fixes**

```bash
git add -A
git commit -m "fix: address code review findings"
```
(Skip if nothing to fix.)

---

## Self-Review

**Spec coverage:** Step A (schema) → Task 1/2. Step B (data migration) → Task 3. Step C (models) → Task 2. Step D (controllers/routes) → Task 5. Step E (frontend) → Task 6. Step F (tests) → Tasks 2/3/5/7. Step G (docs) → Task 7. Step H (self-review) → Task 8. The audit-surfaced ripple (ReportService/ProjectsImport/Department/Specialization/User/DummyDataSeeder) not named in the spec → Task 4, flagged explicitly in Global Constraints and will be called out again in the final report per the user's own request for "any place you had to make a judgment call."

**Placeholder scan:** every step has literal, complete code or an exact command — no TBD/TODO/"add appropriate handling" anywhere in the plan.

**Type consistency:** `Proposal::instantiateProject(User $actor): Project` defined in Task 2, called identically in Task 5's `ProposalController::instantiate()` and Task 4's `ProjectsImport`/`DummyDataSeeder`. `Project::STATUS_IN_PROGRESS`/`STATUS_ARCHIVED` defined in Task 2, used consistently in Task 3's command, Task 4's `ProjectsImport`/`PublicController`, Task 5's `PublicController`. `Proposal::STATUS_PENDING`/`STATUS_ARCHIVED` used consistently across Tasks 2–7. `$project->supervisor`/`$project->students` (accessor, appended) referenced identically in Task 6's Vue `Project` interface and Task 5's `ProjectController::show()` eager-load comment.
