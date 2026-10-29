<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to boot the suite when it would hit the real database.
     *
     * `phpunit.xml` points tests at sqlite `:memory:`, but a cached config file
     * (`bootstrap/cache/config.php`) bypasses those env vars and silently sends
     * `RefreshDatabase` at the MySQL database named in `.env`. That drops every
     * table in the development database, so guard against it explicitly.
     *
     * @return Application
     */
    public function createApplication()
    {
        if (file_exists(dirname(__DIR__).'/bootstrap/cache/config.php')) {
            throw new RuntimeException(
                'Configuration is cached, so tests would use the real database. Run `php artisan config:clear` before running the suite.',
            );
        }

        $app = parent::createApplication();

        if (config('database.default') !== 'sqlite') {
            throw new RuntimeException(
                'Tests must run on sqlite (see phpunit.xml); got ['.config('database.default').'].',
            );
        }

        return $app;
    }
}
