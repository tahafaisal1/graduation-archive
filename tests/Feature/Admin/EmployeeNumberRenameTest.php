<?php

use Illuminate\Support\Facades\Schema;

test('users table has employee_number and not registration_number', function () {
    expect(Schema::hasColumn('users', 'employee_number'))->toBeTrue();
    expect(Schema::hasColumn('users', 'registration_number'))->toBeFalse();
});
