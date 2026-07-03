<?php

use App\Imports\ProjectsImport;
use App\Models\Department;
use App\Models\Project;
use App\Models\Specialization;
use Database\Seeders\ProjectStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
    $this->seed(ProjectStatusSeeder::class);
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
    ];

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->fromArray([$headers], null, 'A1');

    foreach ($rows as $i => $row) {
        $rowData = array_map(fn ($h) => $row[$h] ?? '', $headers);
        $sheet->fromArray([$rowData], null, 'A' . ($i + 2));
    }

    $path = sys_get_temp_dir() . '/import_test_' . uniqid() . '.xlsx';
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
    $dept       = Department::factory()->create(['code' => 'CS']);
    $spec       = Specialization::factory()->create([
        'name'          => 'Software Engineering',
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
        'project_title'       => 'Test Import Project',
        'description'         => 'A test description',
        'academic_year'       => '2023/2024',
        'department_code'     => $deps['dept']->code,
        'specialization_name' => $deps['spec']->name,
        'supervisor_email'    => $deps['supervisor']->email,
        'student_1_name'      => 'Ahmed Ali',
        'student_1_reg'       => 'ST001',
        'student_2_name'      => '',
        'student_2_reg'       => '',
        'student_3_name'      => '',
        'student_3_reg'       => '',
        'final_score'         => '85.50',
    ], $overrides);
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
    $deps   = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps)]));

    $this->assertDatabaseHas('projects', [
        'project_title'     => 'Test Import Project',
        'academic_year'     => '2023/2024',
        'current_status_id' => 1, // archived
        'is_deleted'        => false,
    ]);

    expect($import->getSummary()['success_count'])->toBe(1);
});

// ── 5. Per-row failure isolation ───────────────────────────────────────────────

test('invalid department_code fails that row only', function () {
    $deps   = makeImportDeps();
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
    $deps   = makeImportDeps();
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
    $deps   = makeImportDeps();
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
    $deps   = makeImportDeps();
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
    $deps   = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'student_1_name' => 'Ahmed Ali',
        'student_1_reg'  => 'ST001',
        'student_2_name' => 'Fatima Hassan',
        'student_2_reg'  => 'ST002',
        'student_3_name' => 'Omar Khalid',
        'student_3_reg'  => 'ST003',
    ])]));

    $project = Project::where('project_title', 'Test Import Project')->first();
    expect($project)->not->toBeNull()
        ->and($project->students()->count())->toBe(3);
});

test('empty student slots are skipped not treated as errors', function () {
    $deps   = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps, [
        'student_1_name' => 'Ahmed Ali',
        'student_1_reg'  => 'ST001',
        'student_2_name' => '',
        'student_2_reg'  => '',
        'student_3_name' => '',
        'student_3_reg'  => '',
    ])]));

    $project = Project::where('project_title', 'Test Import Project')->first();
    expect($project)->not->toBeNull()
        ->and($project->students()->count())->toBe(1);

    expect($import->getSummary()['failed_count'])->toBe(0);
});

// ── 7. Summary counts ─────────────────────────────────────────────────────────

test('import summary shows correct success and fail counts', function () {
    $deps   = makeImportDeps();
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
    $deps   = makeImportDeps();
    $import = new ProjectsImport(dryRun: true);
    Excel::import($import, makeImportFile([validImportRow($deps)]));

    $summary = $import->getSummary();
    expect($summary['success_count'])->toBe(1)
        ->and($summary['preview_rows'])->toHaveCount(1)
        ->and($summary['preview_rows'][0]['valid'])->toBeTrue();

    $this->assertDatabaseCount('projects', 0);
});

test('actual import saves data to database', function () {
    $deps   = makeImportDeps();
    $import = new ProjectsImport(dryRun: false);
    Excel::import($import, makeImportFile([validImportRow($deps)]));

    expect($import->getSummary()['success_count'])->toBe(1);
    $this->assertDatabaseCount('projects', 1);
});
