<?php

namespace App\Http\Controllers;

use App\Exports\ProjectImportTemplate;
use App\Imports\ProjectsImport;
use App\Models\Proposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class ImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Import/Index');
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(new ProjectImportTemplate, 'projects_import_template.xlsx');
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new ProjectsImport(dryRun: true);
        Excel::import($import, $request->file('file'));

        return back()->with('preview', $import->getSummary());
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new ProjectsImport(dryRun: false);
        Excel::import($import, $request->file('file'));

        $summary = $import->getSummary();

        $message = "تم الاستيراد: {$summary['success_count']} مشروع بنجاح";
        if ($summary['failed_count'] > 0) {
            $message .= "، {$summary['failed_count']} صف فشل";
        }

        return back()
            ->with('success', $message)
            ->with('import_summary', $summary);
    }

    public function uploadPdfs(Request $request): RedirectResponse
    {
        $request->validate([
            'zip_file' => ['required', 'file', 'mimes:zip', 'max:51200'],
        ]);

        $zipPath = $request->file('zip_file')->store('temp', 'local');
        $fullPath = storage_path('app/' . $zipPath);

        $zip = new ZipArchive;
        if ($zip->open($fullPath) !== true) {
            return back()->with('error', 'تعذّر فتح ملف ZIP');
        }

        $matched   = 0;
        $unmatched = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }

            $baseName = pathinfo($entry, PATHINFO_FILENAME);
            $proposal = Proposal::where('title', $baseName)
                ->where('is_deleted', false)
                ->first();

            if (! $proposal) {
                $unmatched[] = $baseName;
                continue;
            }

            // Extract PDF content and save to public storage
            $pdfContent  = $zip->getFromIndex($i);
            $storagePath = 'projects/' . uniqid('import_') . '.pdf';
            \Illuminate\Support\Facades\Storage::disk('public')->put($storagePath, $pdfContent);

            // draft_file_path lives on the Proposal (the paper form), not the
            // instantiated Project — see the 2026-08-24 split. There is no
            // per-document tracking any more (project_documents was dropped).
            $proposal->update(['draft_file_path' => $storagePath]);

            $matched++;
        }

        $zip->close();
        \Illuminate\Support\Facades\Storage::disk('local')->delete($zipPath);

        $message = "تم ربط {$matched} ملف PDF بالمشاريع";
        if (count($unmatched) > 0) {
            $message .= '. لم يُطابق: ' . implode(', ', array_slice($unmatched, 0, 5));
            if (count($unmatched) > 5) {
                $message .= ' وآخرون';
            }
        }

        return back()
            ->with('success', $message)
            ->with('pdf_summary', [
                'matched'   => $matched,
                'unmatched' => $unmatched,
            ]);
    }
}
