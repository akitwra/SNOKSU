<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Never inherit Docker's development DB_DATABASE in destructive test migrations.
        $testing = $app['config']->get('database.connections.pgsql');
        $testing['database'] = 'snokgu_test';
        $testing['url'] = null;
        $app['config']->set('database.connections.pgsql_testing', $testing);
        $app['config']->set('database.default', 'pgsql_testing');
        if ($app['config']->get('database.connections.pgsql_testing.database') !== 'snokgu_test') {
            throw new \RuntimeException('Unsafe test database.');
        }

        return $app;
    }
}
