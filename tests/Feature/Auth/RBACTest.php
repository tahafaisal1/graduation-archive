<?php

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
});

test('unauthenticated user is redirected to login from dashboard', function () {
    $this->get('/dashboard')
        ->assertRedirect('/login');
});

test('super_admin can access dashboard', function () {
    $this->seed(AdminSeeder::class);
    $admin = User::where('email', 'admin@admin.com')->first();

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk();
});

test('super_admin can access admin panel', function () {
    $this->seed(AdminSeeder::class);
    $admin = User::where('email', 'admin@admin.com')->first();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();
});

test('dept_staff cannot access admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('dept_staff');

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('dept_manager cannot access admin panel', function () {
    $user = User::factory()->create();
    $user->assignRole('dept_manager');

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('dept_manager can access departments page', function () {
    $user = User::factory()->create();
    $user->assignRole('dept_manager');

    $this->actingAs($user)
        ->get('/departments')
        ->assertOk();
});

test('viewer cannot access departments page', function () {
    $user = User::factory()->create();
    $user->assignRole('viewer');

    $this->actingAs($user)
        ->get('/departments')
        ->assertForbidden();
});

test('dept_staff can access projects create page', function () {
    $user = User::factory()->create();
    $user->assignRole('dept_staff');

    $this->actingAs($user)
        ->get('/projects/create')
        ->assertOk();
});

test('viewer cannot access projects create page', function () {
    $user = User::factory()->create();
    $user->assignRole('viewer');

    $this->actingAs($user)
        ->get('/projects/create')
        ->assertForbidden();
});
