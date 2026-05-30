<?php

// The ERP disables public self-registration — users are created by administrators.
// These Breeze default tests are skipped as they test a feature intentionally removed.

test('registration screen returns 404 (disabled in ERP)', function () {
    $response = $this->get('/register');

    // Public registration is disabled in this ERP; the route does not exist.
    $response->assertStatus(404);
});

test('new users cannot self-register (disabled in ERP)', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    // Route does not exist, returns 404; user is not authenticated.
    $this->assertGuest();
    $response->assertStatus(404);
});
