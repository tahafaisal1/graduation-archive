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

        // Auth::user() is the importing admin in normal (controller-driven)
        // use; when the importer is invoked directly outside an
        // authenticated request (e.g. via Excel::import() in tests), fall
        // back to the row's own supervisor as the instantiating actor —
        // always a real, validated User at this point.
        $project = $proposal->instantiateProject(Auth::user() ?? $supervisor);
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
