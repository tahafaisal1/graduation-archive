<?php

use App\Exports\ProjectImportTemplate;
use App\Imports\ProjectsImport;
use App\Models\Department;
use App\Models\Examiner;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use Database\Seeders\ProjectLifecycleStatusSeeder;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
    $this->seed(ProjectLifecycleStatusSeeder::class);
});

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Builds a real xlsx UploadedFile from an array of row maps.
 * Keys must match the 13 template column names.
 */
function makeImportFile(array $rows): UploadedFile
{
    $headers = [
        'project_title', 'description', 'academic_year', 'department_code',
        'specialization_name', 'supervisor_email',
        'student_1_name', 'student_1_reg',
        'student_2_name', 'student_2_reg',
        'student_3_name', 'student_3_reg',
        'final_score',
        'examiner_1_name', 'examiner_1_notes',
        'examiner_2_name', 'examiner_2_notes',
    ];

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([$headers], null, 'A1');

    foreach ($rows as $i => $row) {
        $rowData = array_map(fn ($h) => $row[$h] ?? '', $headers);
        $sheet->fromArray([$rowData], null, 'A'.($i + 2));
    }

    $path = sys_get_temp_dir().'/import_test_'.uniqid().'.xlsx';
    IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

    return new UploadedFile(
        $path,
        'import.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true, // test mode — bypasses is_uploaded_file check
    );
}

/**
 * Creates dept + spec + supervisor and returns them keyed.
 */
function makeImportDeps(): array
{
    $dept = Department::factory()->create(['code' => 'CS']);
    $spec = Specialization::factory()->create([
        'name' => 'Software Engineering',
        'department_id' => $dept->id,
    ]);
    $supervisor = userWithRole('supervisor');

    return compact('dept', 'spec', 'supervisor');
}

/**
 * Returns a fully valid row array, mergeable with overrides.
 */
function validImportRow(array $deps, array $overrides = []): array
{
    return array_merge([
        'project_title' => 'Test Import Project',
        'description' => 'A test description',
        'academic_year' => '2023/2024',
        'department_code' => $deps['dept']->code,
        'specialization_name' => $deps['spec']->name,
        'supervisor_email' => $deps['supervisor']->email,
        'student_1_name' => 'Ahmed Ali',
        'student_1_reg' => 'ST001',
        'student_2_name' => '',
        'student_2_reg' => '',
        'student_3_name' => '',
        'student_3_reg' => '',
        'final_score' => '85.50',
        'examiner_1_name' => '',
        'examiner_1_notes' => '',
        'examiner_2_name' => '',
        'examiner_2_notes' => '',
    ], $overrides);
}

/**
 * Builds a real .zip UploadedFile containing one PDF per given "title => bytes".
 */
function makePdfZip(array $pdfsByTitle): UploadedFile
{
    $path = sys_get_temp_dir().'/pdf_zip_'.uniqid().'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);
    foreach ($pdfsByTitle as $title => $bytes) {
        $zip->addFromString($title.'.pdf', $bytes);
    }
    $zip->close();

    return new UploadedFile($path, 'pdfs.zip', 'application/zip', null, true);
}

// ── 1. Access control ──────────────────────────────────────────────────────────

test('super_admin can access import page', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('import.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Import/Index'));
});

test('dept_manager cannot access import page', function () {
    $this->actingAs(userWithRole('dept_manager'))
        ->get(route('import.index'))
        ->assertForbidden();
});

// ── 2. Template download ───────────────────────────────────────────────────────

test('template download returns valid xlsx file', function () {
    $this->actingAs(userWithRole('super_admin'))
        ->get(route('import.template'))
        ->assertOk()
        ->assertDownload('projects_import_template.xlsx');
});

test('template headings include the four optional examiner columns', function () {
    $headings = (new ProjectImportTemplate)->headings();

    expect($headings)->toContain('examiner_1_name')
        ->toContain('examiner_1_notes')
        ->toContain('examiner_2_name')
        ->toContain('examiner_2_notes');

    expect(array_search('final_score', $headings))
        ->toBeLessThan(array_search('examiner_1_name', $headings));
});

// ── 3. File validation (HTTP layer) ───────────────────────────────────────────

test('import rejects non-excel files', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs(userWithRole('super_admin'))
        ->from(route('import.index'))
        ->post(route('import.run'), ['file' => $file])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseCount('projects', 0);
});

test('import rejects files larger than 5MB', function () {
    // 6 000 KB > 5 120 KB (max:5120)
    $file = UploadedFile::fake()->create(
        'large.xlsx',
        6000,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );

    $this->actingAs(userWithRole('super_admin'))
        ->from(route('import.index'))
        ->post(route('import.run'), ['file' => $file])
        ->assertSessionHasErrors('file');

    $this->assertDatabaseCount('projects', 0);
});

// ── 4. Valid import (ProjectsImport tested directly) ─────────────────────────
//
// HTTP validation (mimes rule) may behave differently for real xlsx files
// in test environments (finfo may detect xlsx as zip). We test the import
// logic via Excel::import() directly, which is what the controller calls
// after validation passes.
// ─────────────────────────────────────────────────────────────────────────────

test('valid row creates project successfully', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps)]));

    $this->assertDatabaseHas('proposals', [
        'title' => 'Test Import Project',
        'academic_year' => '2023/2024',
        'is_deleted' => false,
    ]);

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project)->not->toBeNull()
        ->and($project->status_id)->toBe(Project::STATUS_ARCHIVED) // final_score present → archived
        ->and($project->is_deleted)->toBeFalse();

    // Lock the post-split import contract the diagnostic flagged as untested:
    $proposal = Proposal::where('title', 'Test Import Project')->first();
    expect($proposal->status_id)->toBe(Proposal::STATUS_ARCHIVED)
        ->and((float) $project->final_score)->toBe(85.5);

    expect($import->getSummary()['success_count'])->toBe(1);
});

// ── 5. Per-row failure isolation ───────────────────────────────────────────────

test('invalid department_code fails that row only', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([
        validImportRow($deps, ['department_code' => 'NONEXISTENT']),
    ]));

    $summary = $import->getSummary();
    expect($summary['success_count'])->toBe(0)
        ->and($summary['failed_count'])->toBe(1)
        ->and($summary['failed_rows'][0]['reason'])->toContain('القسم غير موجود');

    $this->assertDatabaseCount('projects', 0);
});

test('invalid specialization_name fails that row only', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([
        validImportRow($deps, ['specialization_name' => 'Unknown Specialization']),
    ]));

    $summary = $import->getSummary();
    expect($summary['success_count'])->toBe(0)
        ->and($summary['failed_count'])->toBe(1)
        ->and($summary['failed_rows'][0]['reason'])->toContain('التخصص غير موجود');

    $this->assertDatabaseCount('projects', 0);
});

test('invalid supervisor_email fails that row only', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([
        validImportRow($deps, ['supervisor_email' => 'nobody@nowhere.com']),
    ]));

    $summary = $import->getSummary();
    expect($summary['success_count'])->toBe(0)
        ->and($summary['failed_count'])->toBe(1)
        ->and($summary['failed_rows'][0]['reason'])->toContain('المشرف غير موجود');

    $this->assertDatabaseCount('projects', 0);
});

test('missing required field fails that row', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([
        validImportRow($deps, ['project_title' => '']),
    ]));

    $summary = $import->getSummary();
    expect($summary['success_count'])->toBe(0)
        ->and($summary['failed_count'])->toBe(1)
        ->and($summary['failed_rows'][0]['reason'])->toContain('project_title');

    $this->assertDatabaseCount('projects', 0);
});

// ── 6. Students ────────────────────────────────────────────────────────────────

test('multiple students in one row are created correctly', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'student_1_name' => 'Ahmed Ali',
        'student_1_reg' => 'ST001',
        'student_2_name' => 'Fatima Hassan',
        'student_2_reg' => 'ST002',
        'student_3_name' => 'Omar Khalid',
        'student_3_reg' => 'ST003',
    ])]));

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project)->not->toBeNull()
        ->and($project->proposal->students()->count())->toBe(3);
});

test('empty student slots are skipped not treated as errors', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'student_1_name' => 'Ahmed Ali',
        'student_1_reg' => 'ST001',
        'student_2_name' => '',
        'student_2_reg' => '',
        'student_3_name' => '',
        'student_3_reg' => '',
    ])]));

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project)->not->toBeNull()
        ->and($project->proposal->students()->count())->toBe(1);

    expect($import->getSummary()['failed_count'])->toBe(0);
});

test('row with a valid student name but blank reg cell imports successfully', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'student_1_name' => 'Layla Ahmed',
        'student_1_reg' => '',
    ])]));

    expect($import->getSummary()['failed_count'])->toBe(0)
        ->and($import->getSummary()['success_count'])->toBe(1);

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project)->not->toBeNull()
        ->and($project->proposal->students()->count())->toBe(1)
        ->and($project->proposal->students()->first()->full_name)->toBe('Layla Ahmed')
        ->and($project->proposal->students()->first()->registration_number)->toBeNull();
});

// ── 7. Summary counts ─────────────────────────────────────────────────────────

test('import summary shows correct success and fail counts', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([
        validImportRow($deps, ['project_title' => 'Valid Project One']),
        validImportRow($deps, ['project_title' => 'Valid Project Two']),
        validImportRow($deps, ['department_code' => 'BAD_DEPT']),  // will fail
    ]));

    $summary = $import->getSummary();
    expect($summary['total_rows'])->toBe(3)
        ->and($summary['success_count'])->toBe(2)
        ->and($summary['failed_count'])->toBe(1);

    $this->assertDatabaseCount('projects', 2);
});

// ── 8. Preview vs actual import ────────────────────────────────────────────────

test('preview does not save data to database', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: true);
    Excel::import($import, makeImportFile([validImportRow($deps)]));

    $summary = $import->getSummary();
    expect($summary['success_count'])->toBe(1)
        ->and($summary['preview_rows'])->toHaveCount(1)
        ->and($summary['preview_rows'][0]['valid'])->toBeTrue();

    $this->assertDatabaseCount('projects', 0);
});

test('actual import saves data to database', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps)]));

    expect($import->getSummary()['success_count'])->toBe(1);
    $this->assertDatabaseCount('projects', 1);
});

// ── 9. Examiners + evaluations (Fix 2) ───────────────────────────────────────

test('row with 2 examiner names and 2 notes creates 2 examiners and 2 evaluations', function () {
    $deps = makeImportDeps();
    $admin = userWithRole('super_admin');

    $import = new ProjectsImport(dryRun: false);
    $this->actingAs($admin);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'examiner_1_name' => 'Dr. Khalid',
        'examiner_1_notes' => 'Strong defense',
        'examiner_2_name' => 'Dr. Sara',
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
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'examiner_1_name' => 'Dr. Solo',
        'examiner_1_notes' => 'Good work',
    ])]));

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project->examiners()->count())->toBe(1)
        ->and($project->evaluations()->count())->toBe(1)
        ->and($project->evaluations()->first()->notes)->toBe('Good work');
});

test('row with 1 examiner name and no notes creates 1 examiner and 0 evaluations', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'examiner_1_name' => 'Dr. Nameonly',
        'examiner_1_notes' => '',
    ])]));

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project->examiners()->count())->toBe(1)
        ->and($project->evaluations()->count())->toBe(0);
});

test('row with no examiner columns filled creates project with 0 examiners', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps)]));

    $project = Project::whereHas('proposal', fn ($q) => $q->where('title', 'Test Import Project'))->first();
    expect($project->examiners()->count())->toBe(0)
        ->and($project->evaluations()->count())->toBe(0);
    // import must NOT enforce exactly-2 examiners
    expect($import->getSummary()['failed_count'])->toBe(0);
});

test('same examiner name reused across two rows in one department is not duplicated', function () {
    $deps = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([
        validImportRow($deps, ['project_title' => 'Row A', 'examiner_1_name' => 'Dr. Shared']),
        validImportRow($deps, ['project_title' => 'Row B', 'examiner_1_name' => 'Dr. Shared']),
    ]));

    expect(Examiner::where('full_name', 'Dr. Shared')->count())->toBe(1);

    $shared = Examiner::where('full_name', 'Dr. Shared')->first();
    expect($shared->projects()->count())->toBe(2);
});

// ── 10. PDF upload routing (Fix 3) ──────────────────────────────────────────

test('uploadPdfs sets project final_file_path and not proposal draft_file_path', function () {
    Storage::fake('public');
    $deps = makeImportDeps();

    Excel::import(new ProjectsImport(dryRun: false), makeImportFile([
        validImportRow($deps, ['project_title' => 'Inventory System']),
    ]));

    $proposal = Proposal::where('title', 'Inventory System')->first();
    $project = $proposal->instantiatedProject;

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

    $proposal = Proposal::factory()->create([
        'title' => 'Orphan Proposal',
        'department_id' => $deps['dept']->id,
        'status_id' => Proposal::STATUS_PENDING,
    ]);

    $this->actingAs(userWithRole('super_admin'))
        ->post(route('import.pdfs'), ['zip_file' => makePdfZip(['Orphan Proposal' => '%PDF-1.4 fake'])])
        ->assertRedirect();

    $proposal->refresh();
    expect($proposal->draft_file_path)->toBeNull();

    Log::shouldHaveReceived('warning')->once();
});
