<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('users table has employee_number and not registration_number', function () {
    expect(Schema::hasColumn('users', 'employee_number'))->toBeTrue();
    expect(Schema::hasColumn('users', 'registration_number'))->toBeFalse();
});

test('UserFactory produces employee_number and not registration_number', function () {
    $user = User::factory()->create();
    expect($user->employee_number)->not->toBeNull();
    expect($user->getAttributes())->not->toHaveKey('registration_number');
});
