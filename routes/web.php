<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// All authenticated users
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// super_admin only
Route::middleware(['auth', 'role:super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');
});

// dept_manager or super_admin
Route::middleware(['auth', 'role:dept_manager,super_admin'])->group(function () {
    Route::get('/departments', fn () => Inertia::render('Departments/Index'))->name('departments.index');
});

// dept_staff, dept_manager, or super_admin
Route::middleware(['auth', 'role:dept_staff,dept_manager,super_admin'])->group(function () {
    Route::get('/projects/create', fn () => Inertia::render('Projects/Create'))->name('projects.create');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
