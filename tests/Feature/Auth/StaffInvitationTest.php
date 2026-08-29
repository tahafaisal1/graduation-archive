<?php

use App\Mail\StaffInvitationMail;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

function inviteUrlFor(User $user): string
{
    $plain = StaffInvitation::issueFor($user);

    return StaffInvitation::signedUrlFor($user, $plain);
}

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

test('the invitation mailable has the Arabic subject and renders the setup URL and inviter name', function () {
    $invitee = User::factory()->create(['name' => 'Rania Saleh']);
    $inviter = User::factory()->create(['name' => 'Admin Boss']);
    $url = 'https://example.test/setup-password/abc123?email=rania%40x.test&expires=1&signature=x';

    $mailable = new StaffInvitationMail($invitee, $inviter, $url);

    $mailable->assertHasSubject('دعوة لإنشاء حسابك في منظومة أرشفة مشاريع التخرج');
    $mailable->assertSeeInHtml('Admin Boss');
    $mailable->assertSeeInHtml('إنشاء كلمة المرور');
    $mailable->assertSeeInHtml($url);
});

test('GET setup-password renders the form for a valid unused token', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create(['password' => null, 'is_active' => false, 'name' => 'Nadia F']);
    $user->assignRole('dept_staff');

    $this->get(inviteUrlFor($user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/SetupPassword')
            ->where('user.name', 'Nadia F')
            ->where('user.email', $user->email));
});

test('tampering the email in the URL does not render the form', function () {
    $user = User::factory()->create(['password' => null]);
    $url = inviteUrlFor($user);
    $tampered = str_replace(urlencode($user->email), urlencode('attacker@evil.test'), $url);

    $this->get($tampered)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
});

test('a validly-signed URL with an unknown token renders the error page', function () {
    $user = User::factory()->create(['password' => null]);
    StaffInvitation::issueFor($user);
    $bogus = URL::temporarySignedRoute('staff.setup-password', now()->addHour(), [
        'token' => 'totally-not-a-real-token', 'email' => $user->email,
    ]);

    $this->get($bogus)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
});

test('an expired invitation renders the error page', function () {
    $user = User::factory()->create(['password' => null]);
    $plain = StaffInvitation::issueFor($user);
    $url = URL::temporarySignedRoute('staff.setup-password', now()->addHour(), [
        'token' => $plain, 'email' => $user->email,
    ]);
    StaffInvitation::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

    $this->get($url)->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
});

test('a used invitation renders the error page even if not expired', function () {
    $user = User::factory()->create(['password' => null]);
    $plain = StaffInvitation::issueFor($user);
    $url = StaffInvitation::signedUrlFor($user, $plain);
    StaffInvitation::where('user_id', $user->id)->update(['used_at' => now()]);

    $this->get($url)->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
});

test('the setup route is not reachable by an authenticated session', function () {
    $user = User::factory()->create(['password' => null]);
    $url = inviteUrlFor($user);

    $this->actingAs(User::factory()->create())->get($url)->assertRedirect(route('dashboard'));
});

test('a valid submit sets the password, activates the user, marks the token used, and logs in', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create(['password' => null, 'is_active' => false, 'name' => 'Sami K']);
    $user->assignRole('supervisor');
    $plain = StaffInvitation::issueFor($user);
    $url = StaffInvitation::signedUrlFor($user, $plain);

    $this->post($url, [
        'email' => $user->email,
        'password' => 'Str0ng-pass-9',
        'password_confirmation' => 'Str0ng-pass-9',
    ])->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->password)->not->toBeNull();
    expect($user->is_active)->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
    expect(StaffInvitation::where('user_id', $user->id)->first()->used_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('reusing the URL after a successful setup shows the error page and does not re-login', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create(['password' => null, 'is_active' => false]);
    $user->assignRole('dept_staff');
    $plain = StaffInvitation::issueFor($user);
    $url = StaffInvitation::signedUrlFor($user, $plain);

    $this->post($url, ['email' => $user->email, 'password' => 'Str0ng-pass-9', 'password_confirmation' => 'Str0ng-pass-9']);
    auth()->logout();

    $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
    $this->post($url, ['email' => $user->email, 'password' => 'Another-pass-1', 'password_confirmation' => 'Another-pass-1'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/InvitationInvalid'));
    $this->assertGuest();
});

test('the setup POST is rate limited to 5 per hour per IP', function () {
    $user = User::factory()->create(['password' => null]);
    $plain = StaffInvitation::issueFor($user);
    $url = StaffInvitation::signedUrlFor($user, $plain);
    StaffInvitation::where('user_id', $user->id)->update(['used_at' => now()]);

    foreach (range(1, 5) as $i) {
        $this->post($url, ['email' => $user->email, 'password' => 'Str0ng-pass-9', 'password_confirmation' => 'Str0ng-pass-9']);
    }
    $this->post($url, ['email' => $user->email, 'password' => 'Str0ng-pass-9', 'password_confirmation' => 'Str0ng-pass-9'])
        ->assertStatus(429);
});

test('the invited user cannot log in before completing setup', function () {
    User::factory()->create(['password' => null, 'is_active' => false, 'email' => 'locked@test.local']);

    $this->post('/login', ['email' => 'locked@test.local', 'password' => 'anything'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('a deactivated user with a password cannot log in', function () {
    User::factory()->create([
        'email' => 'off@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('secret-pass-1'),
        'is_active' => false,
    ]);

    $this->post('/login', ['email' => 'off@test.local', 'password' => 'secret-pass-1'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});
