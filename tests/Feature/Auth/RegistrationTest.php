<?php

test('registration screen redirects to login', function () {
    $this->get('/register')
        ->assertRedirect(route('login'));
});

test('registration post redirects to login', function () {
    $this->post('/register', [
        'name'                  => 'Test User',
        'email'                 => 'test@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('login'));

    $this->assertGuest();
});
