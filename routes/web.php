<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\ExaminerController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectExaminerController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SpecializationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public — no auth required
Route::get('/', [PublicController::class, 'index'])->name('home');
Route::get('/browse', [PublicController::class, 'browse'])->name('public.browse');
Route::get('/browse/{id}', [PublicController::class, 'show'])->name('public.show');

// Temporary dev-only design reference — remove before production
Route::get('/design-system', function () {
    return Inertia::render('DesignSystem');
})->name('design-system');

// All authenticated users
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
});

// Reports — dept_manager + super_admin
Route::middleware(['auth', 'role:dept_manager,super_admin'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/department',      [ReportController::class, 'departmentReport'])    ->name('department');
    Route::get('/specializations', [ReportController::class, 'specializationReport'])->name('specializations');
    Route::get('/supervisors',     [ReportController::class, 'supervisorReport'])    ->name('supervisors');
    Route::get('/yearly',          [ReportController::class, 'yearlyReport'])        ->name('yearly');
    Route::get('/export/pdf',      [ReportController::class, 'exportPdf'])           ->name('export.pdf');
    Route::get('/export/excel',    [ReportController::class, 'exportExcel'])         ->name('export.excel');
});

// super_admin only
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');
    Route::resource('users', AdminUserController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::patch('users/{user}/toggle-active', [AdminUserController::class, 'toggleActive'])
        ->name('users.toggle-active');
});

// super_admin + dept_manager — department view/edit (ownership enforced in controller for update/edit)
Route::middleware(['auth', 'role:super_admin,dept_manager'])->group(function () {
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::patch('/departments/{department}', [DepartmentController::class, 'update']);

    Route::resource('specializations', SpecializationController::class)
        ->only(['store', 'update', 'destroy']);

    Route::resource('examiners', ExaminerController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::post('projects/{id}/assign-examiner', [ProjectExaminerController::class, 'assign'])
        ->name('projects.assign-examiner');

    Route::delete('projects/{id}/examiners/{examinerId}', [ProjectExaminerController::class, 'remove'])
        ->name('projects.remove-examiner');

    Route::post('projects/{id}/evaluation', [EvaluationController::class, 'store'])
        ->name('projects.evaluation');

    Route::patch('projects/{id}/score', [EvaluationController::class, 'updateScore'])
        ->name('projects.score');
});

// Projects — all authenticated users can browse; role checks handled in controller/form requests
Route::middleware(['auth'])->group(function () {
    Route::resource('projects', ProjectController::class);
    Route::post('projects/{id}/approve', [ProjectController::class, 'approve'])
        ->middleware('role:dept_manager,super_admin')
        ->name('projects.approve');

    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('search/suggestions', [SearchController::class, 'suggestions'])->name('search.suggestions');
});

// super_admin only — create and delete departments
Route::middleware(['auth', 'role:super_admin'])->group(function () {
    Route::get('/departments/create', [DepartmentController::class, 'create'])->name('departments.create');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
});

// Bulk import — super_admin only
Route::middleware(['auth', 'role:super_admin'])->prefix('import')->name('import.')->group(function () {
    Route::get('/', [ImportController::class, 'index'])->name('index');
    Route::get('/template', [ImportController::class, 'downloadTemplate'])->name('template');
    Route::post('/preview', [ImportController::class, 'preview'])->name('preview');
    Route::post('/run', [ImportController::class, 'import'])->name('run');
    Route::post('/pdfs', [ImportController::class, 'uploadPdfs'])->name('pdfs');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
