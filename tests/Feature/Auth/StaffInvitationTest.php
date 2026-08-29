<?php

use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('staff_invitations schema exists and users.password is nullable', function () {
    expect(Schema::hasTable('staff_invitations'))->toBeTrue();
    expect(Schema::hasColumns('staff_invitations', ['user_id', 'token_hash', 'expires_at', 'used_at', 'created_at']))->toBeTrue();

    $user = User::factory()->create(['password' => null]);
    expect($user->fresh()->password)->toBeNull();
});

test('issueFor stores a hashed token, 24h expiry, and returns the plain token', function () {
    $user = User::factory()->create();
    $plain = StaffInvitation::issueFor($user);

    expect($plain)->toBeString()->and(mb_strlen($plain))->toBeGreaterThanOrEqual(40);
    $row = StaffInvitation::where('user_id', $user->id)->firstOrFail();
    expect($row->token_hash)->toBe(hash('sha256', $plain));
    expect($row->used_at)->toBeNull();
    expect(now()->diffInHours($row->expires_at))->toBeGreaterThanOrEqual(23);
});

test('issueFor replaces any prior invitation for the same user', function () {
    $user = User::factory()->create();
    $first = StaffInvitation::issueFor($user);
    $second = StaffInvitation::issueFor($user);

    expect(StaffInvitation::where('user_id', $user->id)->count())->toBe(1);
    expect(StaffInvitation::where('token_hash', hash('sha256', $first))->exists())->toBeFalse();
    expect(StaffInvitation::where('token_hash', hash('sha256', $second))->exists())->toBeTrue();
});
