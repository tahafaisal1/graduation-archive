<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\Department;
use App\Models\Project;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function dashboard(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $stats = match (true) {
            $user->hasRole('super_admin')  => $this->reportService->getDashboardStats(),
            $user->hasRole('dept_manager') => $this->deptManagerDashboard($user),
            default                        => ['total_projects' => Project::where('is_deleted', false)->count()],
        };

        return Inertia::render('Dashboard', ['stats' => $stats]);
    }

    public function departmentReport(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user   = $request->user();
        $deptId = null;

        if ($user->hasRole('dept_manager')) {
            $deptId = $user->department_id;
        } elseif ($user->hasRole('super_admin') && $request->filled('department_id')) {
            $deptId = (int) $request->department_id;
        }

        return Inertia::render('Reports/Department', [
            'report'      => $this->reportService->getDepartmentReport($deptId),
            'departments' => $user->hasRole('super_admin')
                ? Department::orderBy('name')->get(['id', 'name'])
                : [],
            'filter' => ['department_id' => $deptId],
        ]);
    }

    public function specializationReport(): Response
    {
        return Inertia::render('Reports/Specializations', [
            'report' => $this->reportService->getSpecializationTrends(),
        ]);
    }

    public function supervisorReport(): Response
    {
        return Inertia::render('Reports/Supervisors', [
            'report' => $this->reportService->getSupervisorReport(),
        ]);
    }

    public function yearlyReport(): Response
    {
        return Inertia::render('Reports/Yearly', [
            'report' => $this->reportService->getYearlyComparisonReport(),
        ]);
    }

    public function exportPdf(Request $request): \Illuminate\Http\Response
    {
        $type = $request->input('type', 'department');

        [$title, $headers, $rows] = $this->buildExportData($type);

        $pdf = Pdf::loadView('exports.report', compact('title', 'headers', 'rows'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("{$type}-report.pdf");
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $type = $request->input('type', 'department');

        [$title, $headers, $rows] = $this->buildExportData($type);

        return Excel::download(new ReportExport($title, $headers, $rows), "{$type}-report.xlsx");
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function deptManagerDashboard(\App\Models\User $user): array
    {
        $report = $this->reportService->getDepartmentReport($user->department_id);
        $dept   = collect($report['departments'])->first();

        return [
            'project_count'   => $dept['project_count'] ?? 0,
            'avg_score'       => $dept['avg_score'] ?? null,
            'specializations' => $dept['specializations'] ?? [],
            'supervisors'     => $report['supervisors'],
        ];
    }

    private function buildExportData(string $type): array
    {
        return match ($type) {
            'specializations' => $this->specializationExportData(),
            'supervisors'     => $this->supervisorExportData(),
            'yearly'          => $this->yearlyExportData(),
            default           => $this->departmentExportData(),
        };
    }

    private function departmentExportData(): array
    {
        $report  = $this->reportService->getDepartmentReport();
        $headers = ['القسم', 'الرمز', 'عدد المشاريع', 'متوسط الدرجة', 'عدد المقيَّمين'];
        $rows    = collect($report['departments'])->map(fn ($d) => [
            $d['name'],
            $d['code'],
            $d['project_count'],
            $d['avg_score'] ?? '—',
            $d['scored_count'],
        ])->all();

        return ['تقرير الأقسام', $headers, $rows];
    }

    private function specializationExportData(): array
    {
        $report  = $this->reportService->getSpecializationTrends();
        $headers = ['التخصص', 'القسم', 'عدد المشاريع'];
        $rows    = $report['top_specializations']->map(fn ($s) => [
            $s->name,
            $s->department?->name ?? '—',
            $s->project_count,
        ])->all();

        return ['تقرير التخصصات', $headers, $rows];
    }

    private function supervisorExportData(): array
    {
        $report  = $this->reportService->getSupervisorReport();
        $headers = ['المشرف', 'القسم', 'عدد المشاريع', 'متوسط الدرجة'];
        $rows    = collect($report)->map(fn ($s) => [
            $s['name'],
            $s['department'] ?? '—',
            $s['project_count'],
            $s['avg_score'] ?? '—',
        ])->all();

        return ['تقرير المشرفين', $headers, $rows];
    }

    private function yearlyExportData(): array
    {
        $report  = $this->reportService->getYearlyComparisonReport();
        $headers = ['السنة الأكاديمية', 'عدد المشاريع', 'نسبة النمو %'];
        $rows    = collect($report['yearly'])->map(fn ($y) => [
            $y['year'],
            $y['count'],
            $y['growth_pct'] !== null ? $y['growth_pct'] . '%' : '—',
        ])->all();

        return ['التقرير السنوي', $headers, $rows];
    }
}
