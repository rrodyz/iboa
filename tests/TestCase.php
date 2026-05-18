<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * When running via `php artisan test`, the Laravel app boots once with
     * APP_ENV=local / DB_CONNECTION=mysql (from .env) BEFORE PHPUnit can apply
     * phpunit.xml <env> overrides.
     *
     * We set the critical vars in $_ENV (which phpdotenv v5 immutable checks)
     * BEFORE calling parent::setUp() so that any fresh createApplication() call
     * inside the parent sees sqlite / testing / array drivers instead of the
     * production values.
     *
     * After parent::setUp() we also patch the already-bound IoC key 'env' so
     * that VerifyCsrfToken::runningUnitTests() returns true (CSRF bypassed).
     */
    protected function setUp(): void
    {
        // ── Override env vars before the app (re-)bootstraps ─────────────────
        $overrides = [
            'APP_ENV'          => 'testing',
            'DB_CONNECTION'    => 'sqlite',
            'DB_DATABASE'      => ':memory:',
            'DB_URL'           => '',
            'SESSION_DRIVER'   => 'array',
            'CACHE_STORE'      => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER'      => 'array',
            'BCRYPT_ROUNDS'    => '4',
        ];

        foreach ($overrides as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }

        parent::setUp();

        // Patch the already-resolved IoC 'env' binding so
        // VerifyCsrfToken::runningUnitTests() returns true for all HTTP tests.
        $this->app['env'] = 'testing';
    }
}
