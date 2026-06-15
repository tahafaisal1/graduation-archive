<?php

use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
    $this->seed([RoleSeeder::class, AdminSeeder::class]);
});

test('admin can login with correct credentials', function () {
    $this->post('/login', [
        'email'    => 'admin@admin.com',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
});

test('wrong credentials are rejected', function () {
    $response = $this->post('/login', [
        'email'    => 'admin@admin.com',
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('after login redirects to dashboard', function () {
    $response = $this->post('/login', [
        'email'    => 'admin@admin.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
});
