<?php

use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
});

test('all 5 roles exist in database', function () {
    $roles = ['super_admin', 'dept_manager', 'supervisor', 'dept_staff', 'viewer'];

    foreach ($roles as $role) {
        expect(Role::where('name', $role)->exists())->toBeTrue("Role [{$role}] not found");
    }
});

test('exactly 5 roles are seeded', function () {
    expect(Role::count())->toBe(5);
});
