# Excel Import Feature Closeout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the 5 known semantic/technical gaps in the Excel bulk-import feature so historical fully-archived projects import with examiners, evaluations, and final PDFs preserved — without imposing the in-system finalize invariants.

**Architecture:** Extend the existing `maatwebsite/excel` template + `ProjectsImport` collection importer with 4 optional examiner columns and find-or-create Examiner/Evaluation writes per row. Route `uploadPdfs()` PDFs to `Project::final_file_path` (matching finalized in-system projects) instead of the proposal draft path. Make `proposal_students.registration_number` nullable via migration. Fix a `/storage/null` link and correct wizard copy/flow in `Import/Index.vue`.

**Tech Stack:** Laravel 12, PHP 8.2, `maatwebsite/excel` ^3.1 (3.1.69), Vue 3 + Inertia, Pest 3, SQLite in-memory for tests.

**Spec:** The user's message in this session (the 5-fix scope). No separate spec file.

## Global Constraints

- Import is a DISTINCT code path from the in-system finalize flow. It deliberately does NOT enforce exactly-2-examiners + score + PDF. 0, 1, or 2 examiners per row are all valid.
- Do NOT add per-examiner score columns. `final_score` stays the single project-level grade.
- Do NOT delete existing tests. Add new ones. All baseline Import tests must stay green.
- Do NOT enforce "exactly 2" for imports.
- Leave the `[EXAMPLE]` silent-skip behavior as-is (add a UI note only).
- New template columns (exact names, order, AFTER `final_score`): `examiner_1_name`, `examiner_1_notes`, `examiner_2_name`, `examiner_2_notes` — all OPTIONAL.
- `assigned_by` on `project_examiners` = `Auth::id()` (null when unauthenticated — matches `ProjectExaminerController::assign()`).
- Examiner de-dup key: `Examiner::firstOrCreate(['full_name' => <trimmed>, 'department_id' => <row department id>])`.
- PDF storage path for imported final files: `projects/final/` on the `public` disk (matches `ProjectController::finalize()`).
- Arabic UI copy only in `.vue` templates; English only in PHP/TS code.
- Run `vendor/bin/pint` (or `php artisan pint`) on changed PHP files before each commit. Composer/artisan in this worktree need `--ignore-platform-req=ext-gd` for `composer` only; artisan/tests run fine.
- Test command: `php artisan test` (compact: `php artisan test --compact`). Single file: `php artisan test tests/Feature/Import/ImportTest.php`.

---

## File Structure

| File | Responsibility | Change |
|---|---|---|
| `database/migrations/2026_09_06_000100_make_proposal_students_registration_number_nullable.php` | Make the reg column optional | Create |
| `app/Exports/ProjectImportTemplate.php` | Template headings + example row | Modify — 4 new headings + 4 example cells |
| `app/Imports/ProjectsImport.php` | Per-row proposal/project/student/examiner/evaluation creation | Modify — examiner+evaluation block, imports |
| `app/Http/Controllers/ImportController.php` | `uploadPdfs()` PDF→project routing | Modify — target `Project::final_file_path`, comment, edge-case log |
| `resources/js/pages/Import/Index.vue` | Import wizard UI | Modify — column table rows, step-3 copy, wizard watcher, `[EXAMPLE]` note |
| `resources/js/pages/Projects/Show.vue` | Project detail page | Modify — guard the final-file card with `v-if="project.final_file_path"` |
| `tests/Feature/Import/ImportTest.php` | Import feature tests | Modify — helper header/row keys, new tests, extra assertions |
| `CLAUDE.md` / `PROGRESS.md` | Docs | Modify — record the import semantics + model asymmetry |

---

## Task 1: Make `proposal_students.registration_number` nullable (Fix 4a)

**Files:**
- Create: `database/migrations/2026_09_06_000100_make_proposal_students_registration_number_nullable.php`
- Test: `tests/Feature/Import/ImportTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `proposal_students.registration_number` is nullable. `ProposalStudent` rows may have `registration_number = null`.

**Context:** `app/Imports/ProjectsImport.php:111` already does `trim(...) ?: null` when creating a student — so a valid student name + blank reg cell ALREADY attempts a NULL insert and crashes against the current NOT NULL column. Option A (nullable migration) chosen: the codebase uses small standalone reversible migrations (e.g. `2026_08_29_000100_rename_...`), there is no code path that relies on the NOT NULL constraint, and Option B would silently swallow blank cells.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Import/ImportTest.php` (after the students section, ~line 276):

```php
test('row with a valid student name but blank reg cell imports successfully', function () {
    $deps   = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'student_1_name' => 'Layla Ahmed',
        'student_1_reg'  => '',
    ])]));

    expect($import->getSummary()['failed_count'])->toBe(0)
        ->and($import->getSummary()['success_count'])->toBe(1);

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project)->not->toBeNull()
        ->and($project->proposal->students()->count())->toBe(1)
        ->and($project->proposal->students()->first()->full_name)->toBe('Layla Ahmed')
        ->and($project->proposal->students()->first()->registration_number)->toBeNull();
});
```

- [ ] **Step 2: Run the test, verify it fails**

Run: `php artisan test tests/Feature/Import/ImportTest.php --filter="blank reg cell"`
Expected: FAIL — `SQLSTATE... NOT NULL constraint failed: proposal_students.registration_number` (row counted as failure, or exception).

- [ ] **Step 3: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_students', function (Blueprint $table) {
            $table->string('registration_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('proposal_students', function (Blueprint $table) {
            $table->string('registration_number')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 4: Run the test, verify it passes**

Run: `php artisan test tests/Feature/Import/ImportTest.php --filter="blank reg cell"`
Expected: PASS

- [ ] **Step 5: Run the full Import file, verify no regression**

Run: `php artisan test tests/Feature/Import/ImportTest.php`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint database/migrations/2026_09_06_000100_make_proposal_students_registration_number_nullable.php
git add database/migrations tests/Feature/Import/ImportTest.php
git commit -m "fix: make proposal_students.registration_number nullable for historical import

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 2: Guard the final-file card against `/storage/null` (Fix 4b)

**Files:**
- Modify: `resources/js/pages/Projects/Show.vue:216-221`

**Interfaces:**
- Consumes: `project.final_file_path: string | null` (already in the `Project` interface at line 28).
- Produces: the "الملف النهائي" download card renders only when `project.final_file_path` is truthy.

**Context:** Imported historical projects are set to `Project::STATUS_ARCHIVED`, so `isFinalized` is `true`, so the `v-else` branch (lines 216-221) renders `<a :href="'/storage/' + project.final_file_path">` = `/storage/null` when no PDF was uploaded. `Public/Show.vue:150` already guards its PDF card with `v-if="project.final_file_path || project.proposal.draft_file_path"`.

- [ ] **Step 1: Apply the edit**

Current (lines 196 + 216):
```vue
                    <div v-if="!isFinalized" class="rounded-xl border ...">
                        ... upload card ...
                    </div>
                    <div v-else class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">الملف النهائي</h2>
                        <a :href="'/storage/' + project.final_file_path" target="_blank" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                            تحميل الملف النهائي
                        </a>
                    </div>
```

Change the `v-else` to `v-else-if="project.final_file_path"`:
```vue
                    <div v-else-if="project.final_file_path" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-4 text-base font-semibold text-gray-800 dark:text-gray-200">الملف النهائي</h2>
                        <a :href="'/storage/' + project.final_file_path" target="_blank" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                            تحميل الملف النهائي
                        </a>
                    </div>
```

(An archived project with no final file now renders neither card — the same "just don't show it" behavior as `Public/Show.vue`.)

- [ ] **Step 2: Build**

Run: `npm run build`
Expected: succeeds, no type errors.

- [ ] **Step 3: Lint**

Run: `npx eslint resources/js/pages/Projects/Show.vue`
Expected: clean.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Projects/Show.vue
git commit -m "fix: hide final-file card on Projects/Show when final_file_path is null

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 3: Add optional examiner columns to the template (Fix 1)

**Files:**
- Modify: `app/Exports/ProjectImportTemplate.php`
- Modify: `resources/js/pages/Import/Index.vue` (the `columns` reference array only)
- Test: `tests/Feature/Import/ImportTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `(new ProjectImportTemplate)->headings()` returns 17 columns ending with `examiner_1_name`, `examiner_1_notes`, `examiner_2_name`, `examiner_2_notes`. The downloaded xlsx has these as columns N–Q.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Import/ImportTest.php` (after the template download test, ~line 120):

```php
test('template headings include the four optional examiner columns', function () {
    $headings = (new App\Exports\ProjectImportTemplate)->headings();

    expect($headings)->toContain('examiner_1_name')
        ->toContain('examiner_1_notes')
        ->toContain('examiner_2_name')
        ->toContain('examiner_2_notes');

    // must come after final_score
    expect(array_search('final_score', $headings))
        ->toBeLessThan(array_search('examiner_1_name', $headings));
});
```

- [ ] **Step 2: Run it, verify it fails**

Run: `php artisan test tests/Feature/Import/ImportTest.php --filter="examiner columns"`
Expected: FAIL — `examiner_1_name` not in headings.

- [ ] **Step 3: Edit `ProjectImportTemplate.php`**

`headings()` — append after `'final_score'`:
```php
            'final_score',
            'examiner_1_name',
            'examiner_1_notes',
            'examiner_2_name',
            'examiner_2_notes',
```

`array()` — append 4 cells to the single example row after `'85.50'`:
```php
                '85.50',
                'د. خالد العتيبي',
                'أداء ممتاز، عرض تقديمي قوي',
                'د. سارة المطيري',
                'يُنصح بتوسيع فصل النتائج',
```

`styles()` — no change needed (row 1 and row 2 rules apply to the whole row).

- [ ] **Step 4: Run it, verify it passes**

Run: `php artisan test tests/Feature/Import/ImportTest.php --filter="examiner columns"`
Expected: PASS

- [ ] **Step 5: Update the `columns` array in `Import/Index.vue`**

After the `student_3_reg` entry and before `final_score` stays where it is — append the 4 new entries at the END of the `columns` array (after `final_score`):
```ts
    { name: 'final_score',         label: 'الدرجة النهائية',            required: false },
    { name: 'examiner_1_name',     label: 'اسم الممتحن الأول (اختياري)',      required: false },
    { name: 'examiner_1_notes',    label: 'ملاحظات الممتحن الأول (اختياري)',  required: false },
    { name: 'examiner_2_name',     label: 'اسم الممتحن الثاني (اختياري)',     required: false },
    { name: 'examiner_2_notes',    label: 'ملاحظات الممتحن الثاني (اختياري)', required: false },
```

- [ ] **Step 6: Build + lint**

Run: `npm run build && npx eslint resources/js/pages/Import/Index.vue`
Expected: clean.

- [ ] **Step 7: Run the full Import test file**

Run: `php artisan test tests/Feature/Import/ImportTest.php`
Expected: all green (existing 15 + 2 new so far).

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint app/Exports/ProjectImportTemplate.php
git add app/Exports/ProjectImportTemplate.php resources/js/pages/Import/Index.vue tests/Feature/Import/ImportTest.php
git commit -m "feat: add optional examiner_1/2 name+notes columns to import template

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 4: Importer creates examiners + evaluations per row (Fix 2)

**Files:**
- Modify: `app/Imports/ProjectsImport.php`
- Test: `tests/Feature/Import/ImportTest.php` (helpers + new tests)

**Interfaces:**
- Consumes: template columns `examiner_1_name`, `examiner_1_notes`, `examiner_2_name`, `examiner_2_notes` (Task 3). `Examiner` model (`full_name`, `title`, `department_id` fillable), `Evaluation` model (`project_id`, `examiner_id`, `notes` fillable), `Project::examiners()` BelongsToMany with `withPivot('assigned_by')`.
- Produces: after a successful row import, for each non-blank examiner slot: one `Examiner` (find-or-created on `full_name`+`department_id`), one `project_examiners` pivot row (`assigned_by` = `Auth::id()`), and — only when that slot's notes are non-blank — one `evaluations` row.

**Context:** `processRow()` currently ends at `$project->update([...])` then `$this->successCount++` (lines 126-128). Insert the examiner block between the project update and `successCount++`. Blank name → skip the slot entirely (no empty Examiner). 0/1/2 examiners are all valid; no "exactly 2" enforcement.

- [ ] **Step 1: Extend the test helpers**

In `tests/Feature/Import/ImportTest.php`:

`makeImportFile()` `$headers` array — append the 4 names:
```php
        'student_3_name', 'student_3_reg',
        'final_score',
        'examiner_1_name', 'examiner_1_notes',
        'examiner_2_name', 'examiner_2_notes',
    ];
```

`validImportRow()` default array — append the 4 keys as empty strings:
```php
        'final_score'         => '85.50',
        'examiner_1_name'     => '',
        'examiner_1_notes'    => '',
        'examiner_2_name'     => '',
        'examiner_2_notes'    => '',
    ], $overrides);
```

Add `use App\Models\Evaluation;` and `use App\Models\Examiner;` to the top `use` block.

- [ ] **Step 2: Run the full file, verify still green (helper change is inert)**

Run: `php artisan test tests/Feature/Import/ImportTest.php`
Expected: all green.

- [ ] **Step 3: Write the failing tests**

Append to `tests/Feature/Import/ImportTest.php`:

```php
// ── 9. Examiners + evaluations (Fix 2) ───────────────────────────────────────

test('row with 2 examiner names and 2 notes creates 2 examiners and 2 evaluations', function () {
    $deps  = makeImportDeps();
    $admin = userWithRole('super_admin');

    $import = new ProjectsImport(dryRun: false);
    $this->actingAs($admin);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'examiner_1_name'  => 'Dr. Khalid',
        'examiner_1_notes' => 'Strong defense',
        'examiner_2_name'  => 'Dr. Sara',
        'examiner_2_notes' => 'Expand results chapter',
    ])]));

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();

    expect($project->examiners()->count())->toBe(2)
        ->and($project->evaluations()->count())->toBe(2);

    expect(Examiner::where('full_name', 'Dr. Khalid')->where('department_id', $deps['dept']->id)->exists())->toBeTrue();

    $pivot = $project->examiners()->where('full_name', 'Dr. Khalid')->first()->pivot;
    expect($pivot->assigned_by)->toBe($admin->id);

    expect($project->evaluations()->pluck('notes')->all())
        ->toContain('Strong defense')
        ->toContain('Expand results chapter');
});

test('row with 1 examiner name and 1 note creates 1 examiner and 1 evaluation', function () {
    $deps   = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'examiner_1_name'  => 'Dr. Solo',
        'examiner_1_notes' => 'Good work',
    ])]));

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project->examiners()->count())->toBe(1)
        ->and($project->evaluations()->count())->toBe(1)
        ->and($project->evaluations()->first()->notes)->toBe('Good work');
});

test('row with 1 examiner name and no notes creates 1 examiner and 0 evaluations', function () {
    $deps   = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'examiner_1_name'  => 'Dr. Nameonly',
        'examiner_1_notes' => '',
    ])]));

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project->examiners()->count())->toBe(1)
        ->and($project->evaluations()->count())->toBe(0);
});

test('row with no examiner columns filled creates project with 0 examiners', function () {
    $deps   = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps)]));

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project->examiners()->count())->toBe(0)
        ->and($project->evaluations()->count())->toBe(0);
    // import must NOT enforce exactly-2 examiners
    expect($import->getSummary()['failed_count'])->toBe(0);
});

test('same examiner name reused across two rows in one department is not duplicated', function () {
    $deps   = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([
        validImportRow($deps, ['project_title' => 'Row A', 'examiner_1_name' => 'Dr. Shared']),
        validImportRow($deps, ['project_title' => 'Row B', 'examiner_1_name' => 'Dr. Shared']),
    ]));

    expect(Examiner::where('full_name', 'Dr. Shared')->count())->toBe(1);

    $shared = Examiner::where('full_name', 'Dr. Shared')->first();
    expect($shared->projects()->count())->toBe(2);
});
```

- [ ] **Step 4: Run them, verify they fail**

Run: `php artisan test tests/Feature/Import/ImportTest.php --filter="examiner"`
Expected: the 5 new tests FAIL (0 examiners created); `template headings include...` still passes.

- [ ] **Step 5: Implement the examiner block in `ProjectsImport.php`**

Add imports:
```php
use App\Models\Evaluation;
use App\Models\Examiner;
```

In `processRow()`, between `$project->update([...])` and `$this->successCount++;`:
```php
        // Historical archival: 0, 1, or 2 examiners per row are all valid.
        // This is intentionally NOT the in-system finalize invariant
        // (exactly 2 examiners + score + PDF) — imported rows are
        // fully-archived past work whose paper records may be incomplete.
        foreach ([
            ['examiner_1_name', 'examiner_1_notes'],
            ['examiner_2_name', 'examiner_2_notes'],
        ] as [$nameKey, $notesKey]) {
            $examinerName = trim((string) ($row[$nameKey] ?? ''));
            if ($examinerName === '') {
                continue;
            }

            // firstOrCreate on name + department so the same person imported
            // across many rows/files is one Examiner row, not many.
            $examiner = Examiner::firstOrCreate([
                'full_name'     => $examinerName,
                'department_id' => $department->id,
            ]);

            $project->examiners()->attach($examiner->id, ['assigned_by' => Auth::id()]);

            $notes = trim((string) ($row[$notesKey] ?? ''));
            if ($notes !== '') {
                Evaluation::create([
                    'project_id'  => $project->id,
                    'examiner_id' => $examiner->id,
                    'notes'       => $notes,
                ]);
            }
        }
```

- [ ] **Step 6: Run them, verify they pass**

Run: `php artisan test tests/Feature/Import/ImportTest.php --filter="examiner"`
Expected: all PASS.

- [ ] **Step 7: Run the full Import file**

Run: `php artisan test tests/Feature/Import/ImportTest.php`
Expected: all green.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint app/Imports/ProjectsImport.php
git add app/Imports/ProjectsImport.php tests/Feature/Import/ImportTest.php
git commit -m "feat: import creates find-or-created examiners + evaluations per row

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 5: Route uploaded PDFs to `Project::final_file_path` (Fix 3)

**Files:**
- Modify: `app/Http/Controllers/ImportController.php:78-106` (the ZIP loop) and the comment at 100-103
- Test: `tests/Feature/Import/ImportTest.php`

**Interfaces:**
- Consumes: `Proposal::instantiatedProject()` HasOne relation, `Project::final_file_path` fillable.
- Produces: `uploadPdfs()` sets `final_file_path` on the matched proposal's instantiated `Project`, never `draft_file_path`. A proposal with no instantiated project is logged (`Log::warning`) and its PDF added to `unmatched`, no crash.

- [ ] **Step 1: Write the failing tests**

Add a helper near the top of `ImportTest.php` (after `validImportRow`):

```php
/**
 * Builds a real .zip UploadedFile containing one PDF per given "title => bytes".
 */
function makePdfZip(array $pdfsByTitle): UploadedFile
{
    $path = sys_get_temp_dir() . '/pdf_zip_' . uniqid() . '.zip';
    $zip  = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    foreach ($pdfsByTitle as $title => $bytes) {
        $zip->addFromString($title . '.pdf', $bytes);
    }
    $zip->close();

    return new UploadedFile($path, 'pdfs.zip', 'application/zip', null, true);
}
```

Add `use ZipArchive;` and `use Illuminate\Support\Facades\Log;` and `use Illuminate\Support\Facades\Storage;` to the top of the file.

Tests (append):

```php
// ── 10. PDF upload routing (Fix 3) ──────────────────────────────────────────

test('uploadPdfs sets project final_file_path and not proposal draft_file_path', function () {
    Storage::fake('public');
    $deps = makeImportDeps();

    // import one row → proposal + instantiated project
    Excel::import(new ProjectsImport(dryRun: false), makeImportFile([
        validImportRow($deps, ['project_title' => 'Inventory System']),
    ]));

    $proposal = App\Models\Proposal::where('title', 'Inventory System')->first();
    $project  = $proposal->instantiatedProject;

    $this->actingAs(userWithRole('super_admin'))
        ->post(route('import.pdfs'), ['zip_file' => makePdfZip(['Inventory System' => '%PDF-1.4 fake'])])
        ->assertRedirect();

    $project->refresh();
    $proposal->refresh();

    expect($project->final_file_path)->not->toBeNull()
        ->and($proposal->draft_file_path)->toBeNull();

    Storage::disk('public')->assertExists($project->final_file_path);
});

test('uploadPdfs logs a warning and does not crash when proposal has no instantiated project', function () {
    Storage::fake('public');
    Log::spy();
    $deps = makeImportDeps();

    // a bare proposal, never instantiated
    $proposal = App\Models\Proposal::factory()->create([
        'title'         => 'Orphan Proposal',
        'department_id' => $deps['dept']->id,
        'status_id'     => App\Models\Proposal::STATUS_PENDING,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->post(route('import.pdfs'), ['zip_file' => makePdfZip(['Orphan Proposal' => '%PDF-1.4 fake'])])
        ->assertRedirect();

    $proposal->refresh();
    expect($proposal->draft_file_path)->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});
```

- [ ] **Step 2: Run them, verify they fail**

Run: `php artisan test tests/Feature/Import/ImportTest.php --filter="uploadPdfs"`
Expected: first test FAILS (`final_file_path` null, `draft_file_path` set); second FAILS (no warning logged).

> If the `mimes:zip` HTTP validation rejects the real zip in this environment (finfo variance on Windows — same class of issue noted for xlsx in this file), change the two tests to call `app(ImportController::class)->uploadPdfs($request)` with a hand-built `Request`, OR temporarily relax the rule to `['required','file','max:51200']` and assert the extension in the controller. Prefer keeping the HTTP test; only fall back if it is genuinely the environment.

- [ ] **Step 3: Edit `ImportController::uploadPdfs()`**

Replace the matching + storage block (currently lines 85-105). New body of the loop after the extension check:

```php
            $baseName = pathinfo($entry, PATHINFO_FILENAME);
            $proposal = Proposal::with('instantiatedProject')
                ->where('title', $baseName)
                ->where('is_deleted', false)
                ->first();

            if (! $proposal) {
                $unmatched[] = $baseName;
                continue;
            }

            // Imported/historical projects keep their final PDF on
            // Project::final_file_path — the same column in-system finalized
            // projects use, and the column Projects/Show + Public/Show read.
            // (Pre-2026-09 this wrongly wrote proposals.draft_file_path.)
            $project = $proposal->instantiatedProject;

            if (! $project) {
                // Should not happen after the import flow (every imported
                // proposal is instantiated), but a proposal created some
                // other way could match by title — skip, don't crash.
                Log::warning('uploadPdfs: matched proposal has no instantiated project; skipping PDF', [
                    'proposal_id' => $proposal->id,
                    'title'       => $baseName,
                ]);
                $unmatched[] = $baseName;
                continue;
            }

            $pdfContent  = $zip->getFromIndex($i);
            $storagePath = 'projects/final/' . uniqid('import_') . '.pdf';
            Storage::disk('public')->put($storagePath, $pdfContent);

            $project->update(['final_file_path' => $storagePath]);

            $matched++;
```

Update the `use` imports at the top of `ImportController.php`: add
```php
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
```
and drop the two fully-qualified `\Illuminate\Support\Facades\Storage::` references lower down (the ZIP-temp `store`/`delete` calls) in favor of the imported `Storage`. Delete the old comment block at lines 100-103.

- [ ] **Step 4: Run them, verify they pass**

Run: `php artisan test tests/Feature/Import/ImportTest.php --filter="uploadPdfs"`
Expected: PASS.

- [ ] **Step 5: Run the full Import file**

Run: `php artisan test tests/Feature/Import/ImportTest.php`
Expected: all green.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint app/Http/Controllers/ImportController.php
git add app/Http/Controllers/ImportController.php tests/Feature/Import/ImportTest.php
git commit -m "fix: uploadPdfs writes Project.final_file_path, not Proposal.draft_file_path

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 6: UI copy corrections + wizard flow fix (Fix 5)

**Files:**
- Modify: `resources/js/pages/Import/Index.vue`

**Interfaces:**
- Consumes: existing `flash.import_summary` watcher, `currentStep` ref, step-3 template.
- Produces: after import completes the wizard lands on step 3 (results view), not step 4. Step-3 pre-import and post-import copy names both the proposal and the project. Step 1 has an `[EXAMPLE]` note.

- [ ] **Step 1: Fix the wizard watcher**

Current (lines ~72-81):
```ts
watch(
    () => props.flash?.import_summary,
    (val) => {
        if (val) {
            importResult.value = val
            currentStep.value  = 4
        }
    },
    { immediate: true },
)
```
Change `currentStep.value = 4` to `currentStep.value = 3`. (The post-import template on step 3 already has a "رفع ملفات PDF ←" button calling `goToStep(4)` and an "استيراد ملف آخر" reset — the user now chooses.)

- [ ] **Step 2: Fix the step-3 pre-import summary**

Current (lines ~375-380):
```vue
                    <div v-if="previewResult" class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
                        سيتم استيراد <strong>{{ previewResult.success_count }}</strong> مشروع صالح
                        <span v-if="previewResult.failed_count">
                            (سيُتخطى {{ previewResult.failed_count }} صف به أخطاء)
                        </span>.
                    </div>
```
Replace the text:
```vue
                    <div v-if="previewResult" class="rounded-lg bg-blue-50 border border-blue-200 p-4 text-sm text-blue-800">
                        سيتم إنشاء مقترح مؤرشف ومشروع مقابل لكل صف صالح — <strong>{{ previewResult.success_count }}</strong> صف
                        <span v-if="previewResult.failed_count">
                            (سيُتخطى {{ previewResult.failed_count }} صف به أخطاء)
                        </span>.
                    </div>
```

- [ ] **Step 3: Fix the step-3 post-import success card**

Current (lines ~409-412):
```vue
                        <div class="bg-green-50 border border-green-200 rounded-xl p-5 text-center">
                            <div class="text-4xl font-bold text-green-700">{{ importResult.success_count }}</div>
                            <div class="text-sm text-gray-600 mt-1">مشروع تم استيراده بنجاح</div>
                        </div>
```
Change the label line to:
```vue
                            <div class="text-sm text-gray-600 mt-1">مقترح ومشروع تم أرشفتهم بنجاح</div>
```
And the failed card label just below (`صف فشل الاستيراد`) — leave as-is (already correct).

- [ ] **Step 4: Add the `[EXAMPLE]` note to Step 1**

In the Step 1 `<section>`, after the intro `<p>` (the one ending "لا تغيّر أسماء الأعمدة في الصف الأول."), add:
```vue
                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    اترك السطر الأول (المثال) كما هو أو احذفه — لن يُستورد.
                </p>
```

- [ ] **Step 5: Build + lint**

Run: `npm run build && npx eslint resources/js/pages/Import/Index.vue`
Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Import/Index.vue
git commit -m "fix: import wizard lands on results view; copy names proposal+project pair

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 7: Lock the post-split import contract + full-suite green

**Files:**
- Modify: `tests/Feature/Import/ImportTest.php` (`valid row creates project successfully`)

**Interfaces:**
- Consumes: everything above.
- Produces: the diagnostic-flagged untested contract (`proposal.status_id === STATUS_ARCHIVED`, `project.final_score` matches Excel) is now asserted.

- [ ] **Step 1: Extend `valid row creates project successfully`**

Add to that test's assertions:
```php
    $proposal = App\Models\Proposal::where('title', 'Test Import Project')->first();
    expect($proposal->status_id)->toBe(App\Models\Proposal::STATUS_ARCHIVED);

    expect((float) $project->final_score)->toBe(85.5);
```

- [ ] **Step 2: Run the full Import file**

Run: `php artisan test tests/Feature/Import/ImportTest.php`
Expected: all green.

- [ ] **Step 3: Run the FULL suite**

Run: `php artisan test --compact`
Expected: baseline count + new tests, 0 failures. Record the number for the final report.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Import/ImportTest.php
git commit -m "test: lock imported proposal STATUS_ARCHIVED + final_score contract

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 8: Documentation

**Files:**
- Modify: `CLAUDE.md` (Current Status section — add a dated entry at the top of the list)
- Modify: `PROGRESS.md` (Major Changes / Bugfixes section — add a dated entry; update the `proposal_students` schema table `registration_number` nullable → Yes)

**Interfaces:** none.

- [ ] **Step 1: CLAUDE.md — add to "Current Status" (top of the ✅ list)**

A `✅ **Excel Import Closeout — 5 fixes** (branch \`worktree-excel-import-closeout\`)` entry covering:
- Template gains 4 optional columns (`examiner_1_name/notes`, `examiner_2_name/notes`) after `final_score`; no per-examiner score column.
- `ProjectsImport::processRow()` now find-or-creates an `Examiner` (`full_name`+`department_id`) per non-blank slot, attaches `project_examiners` (`assigned_by` = `Auth::id()`), and creates an `evaluations` row when that slot's notes are non-blank. 0/1/2 examiners per row all valid.
- **Deliberate model asymmetry:** import does NOT enforce the in-system finalize invariant (exactly-2-examiners + score + PDF). Historical archival data may be incomplete. Documented here for future readers.
- `ImportController::uploadPdfs()` matches a `Proposal` by title then writes the ZIP entry to its instantiated `Project::final_file_path` (was `Proposal::draft_file_path`); a matched proposal with no instantiated project is `Log::warning`ed and skipped.
- `proposal_students.registration_number` made nullable (migration `2026_09_06_000100_...`) — a valid student name with a blank reg cell now imports.
- `Projects/Show.vue` final-file card guarded with `v-else-if="project.final_file_path"` (was `/storage/null`).
- Import wizard lands on the step-3 results view after import (was auto-jumping to step-4 PDF upload); step-3 copy now names the proposal+project pair.

- [ ] **Step 2: PROGRESS.md — add a "Bugfixes / Corrections" dated entry (2026-09-06)** with the same substance, and in Section 2 update the `proposal_students` table row: `registration_number | varchar(255) | Yes | null` and add a note "nullable since 2026-09-06 (`2026_09_06_000100_...`) — historical imports may lack a student reg number".

- [ ] **Step 3: Commit**

```bash
git add CLAUDE.md PROGRESS.md
git commit -m "docs: record Excel import closeout (examiners, final PDF path, model asymmetry)

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

## Task 9: Verification, manual Playwright, code review, PR

- [ ] **Step 1: superpowers:verification-before-completion** — run `php artisan test --compact` and `npm run build`, paste real output.

- [ ] **Step 2: Manual Playwright verification (separate port, e.g. `php artisan serve --port=8123`)**

Requires MariaDB (`graduation_archive`) reachable and migrated/seeded. Screenshot:
1. Downloaded template open in a viewer / parsed headings — the 4 new examiner columns present.
2. Upload a valid `.xlsx` with mixed row shapes (some examiners, some none, one student with blank reg) — step-3 summary shows correct counts, no crash, lands on results view (not PDF step).
3. `/projects/{new id}` — no `/storage/null` link, examiners section shows imported examiners, score shows.
4. Upload a ZIP of title-matched PDFs → `/projects/{id}` "الملف النهائي" card now shows the download link.
5. `/browse/{id}` still shows the file (Public/Show fallback regression).

Save screenshots under `docs/playwright/2026-09-06-excel-import-closeout/`. If the environment cannot run the browser/DB, record exactly what blocked it in the final report.

- [ ] **Step 3: `/code-review` on the full branch diff (`main...worktree-excel-import-closeout`)**. Triage findings via superpowers:receiving-code-review. Fix real ones, commit.

- [ ] **Step 4: Self-review** the diff for leftovers, then **push** and open a PR against `main` (do NOT merge):

```bash
git push -u origin worktree-excel-import-closeout
gh pr create --base main --title "Excel import closeout: examiners, final PDF path, technical fixes" --body "<per-fix summary + test totals + screenshots>

🤖 Generated with [Claude Code](https://claude.com/claude-code)"
```

- [ ] **Step 5: Report the PR URL.**

---

## Self-Review

**Spec coverage:**
- Fix 1 (template columns) → Task 3. ✓
- Fix 2 (importer examiners+evaluations) → Task 4, all 5 spec test cases covered. ✓
- Fix 3 (PDF → final_file_path) → Task 5, both spec test cases + comment deletion. ✓
- Fix 4a (nullable reg) → Task 1 (Option A + test). ✓
- Fix 4b (/storage/null) → Task 2. ✓
- Fix 5 (UI copy + wizard watcher + [EXAMPLE] note) → Task 6. ✓
- Extra assertions on `valid row creates project successfully` → Task 7. ✓
- Baseline Import tests stay green → checked at the end of Tasks 1,3,4,5,7. ✓
- Docs (import semantics, final_file_path, model asymmetry) → Task 8. ✓
- verification-before-completion + /code-review + PR (no merge) → Task 9. ✓
- Manual Playwright → Task 9 Step 2. ✓

**Placeholder scan:** no TBD/TODO; every code step has literal content.

**Type consistency:** `examiner_1_name`/`examiner_1_notes`/`examiner_2_name`/`examiner_2_notes` used identically in Tasks 3, 4, 6. `Examiner::firstOrCreate(['full_name', 'department_id'])`, `Evaluation::create(['project_id','examiner_id','notes'])`, `attach($id, ['assigned_by' => Auth::id()])` consistent with the models read in prep. `final_file_path` matches the `Project` fillable and the `Projects/Show.vue` interface. Migration filename `2026_09_06_000100_make_proposal_students_registration_number_nullable` used identically in Tasks 1 and 8.
