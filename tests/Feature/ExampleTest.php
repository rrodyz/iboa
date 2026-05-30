<?php

it('redirects unauthenticated users to login page', function () {
    // The ERP root URL redirects to /login for unauthenticated visitors.
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
