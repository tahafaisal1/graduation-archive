<?php

namespace App\Http\Controllers;

use App\Exports\ProjectImportTemplate;
use App\Imports\ProjectsImport;
use App\Models\Proposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        // Resolve via the disk, not storage_path('app/...') — the `local`
        // disk root is storage/app/private, so the hand-built path missed
        // the file and every upload silently failed to open.
        $fullPath = Storage::disk('local')->path($zipPath);

        $zip = new ZipArchive;
        if ($zip->open($fullPath) !== true) {
            return back()->with('error', 'تعذّر فتح ملف ZIP');
        }

        $matched = 0;
        $unmatched = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'pdf') {
                continue;
            }

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
            // (Before 2026-09 this wrongly wrote proposals.draft_file_path.)
            $project = $proposal->instantiatedProject;

            if (! $project) {
                // Should not happen after the import flow (every imported
                // proposal is instantiated), but a proposal created some
                // other way could still match by title — skip, don't crash.
                Log::warning('uploadPdfs: matched proposal has no instantiated project; skipping PDF', [
                    'proposal_id' => $proposal->id,
                    'title' => $baseName,
                ]);
                $unmatched[] = $baseName;

                continue;
            }

            $pdfContent = $zip->getFromIndex($i);
            $storagePath = 'projects/final/'.uniqid('import_').'.pdf';
            Storage::disk('public')->put($storagePath, $pdfContent);

            $project->update(['final_file_path' => $storagePath]);

            $matched++;
        }

        $zip->close();
        Storage::disk('local')->delete($zipPath);

        $message = "تم ربط {$matched} ملف PDF بالمشاريع";
        if (count($unmatched) > 0) {
            $message .= '. لم يُطابق: '.implode(', ', array_slice($unmatched, 0, 5));
            if (count($unmatched) > 5) {
                $message .= ' وآخرون';
            }
        }

        return back()
            ->with('success', $message)
            ->with('pdf_summary', [
                'matched' => $matched,
                'unmatched' => $unmatched,
            ]);
    }
}
