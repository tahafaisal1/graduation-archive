<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed(RoleSeeder::class);
});

// ── 1. View ───────────────────────────────────────────────────────────────────

test('super_admin can view users list', function () {
    $admin = userWithRole('super_admin');

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Users/Index'));
});

// ── 2. Filter ─────────────────────────────────────────────────────────────────

test('super_admin can filter users by role', function () {
    userWithRole('dept_manager');
    userWithRole('dept_staff');
    $admin = userWithRole('super_admin');

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['role' => 'dept_manager']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->where('users.meta.total', 1)
        );
});

// ── 3. Create ─────────────────────────────────────────────────────────────────

test('super_admin can create user with role', function () {
    $admin = userWithRole('super_admin');
    $dept  = Department::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name'          => 'New Staff',
            'email'         => 'newstaff@test.com',
            'password'      => 'password123',
            'role'          => 'dept_staff',
            'department_id' => $dept->id,
            'is_active'     => true,
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('users', ['email' => 'newstaff@test.com']);

    $created = User::where('email', 'newstaff@test.com')->first();
    expect($created->hasRole('dept_staff'))->toBeTrue();
});

// ── 4. Update ─────────────────────────────────────────────────────────────────

test('super_admin can update user info', function () {
    $admin  = userWithRole('super_admin');
    $target = userWithRole('dept_staff');

    $this->actingAs($admin)
        ->patch(route('admin.users.update', $target->id), [
            'name'  => 'Updated Name',
            'email' => $target->email,
            'role'  => 'supervisor',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Updated Name']);
    expect($target->fresh()->hasRole('supervisor'))->toBeTrue();
});

// ── 5. Toggle active ──────────────────────────────────────────────────────────

test('super_admin can toggle user active status', function () {
    $admin  = userWithRole('super_admin');
    $target = userWithRole('dept_staff');

    expect($target->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('admin.users.toggle-active', $target->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($target->fresh()->is_active)->toBeFalse();
});

// ── 6. Guard last super_admin ─────────────────────────────────────────────────

test('super_admin cannot delete the only super_admin', function () {
    $admin = userWithRole('super_admin');

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin->id))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

// ── 7. Delete non-admin ───────────────────────────────────────────────────────

test('super_admin can delete a non-admin user', function () {
    $admin  = userWithRole('super_admin');
    $target = userWithRole('dept_staff');

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $target->id))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

// ── 8. Access control ─────────────────────────────────────────────────────────

test('dept_manager cannot access admin users', function () {
    $manager = userWithRole('dept_manager');

    $this->actingAs($manager)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

// ── 9. Unauthenticated ────────────────────────────────────────────────────────

test('unauthenticated user redirected from admin users', function () {
    $this->get(route('admin.users.index'))
        ->assertRedirect(route('login'));
});
