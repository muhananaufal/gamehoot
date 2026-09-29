<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuses to boot the tests against any database whose name does not end in "_test",
     * because RefreshDatabase drops every table before the first test runs.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        // Reads the configured name only; the PDO connection is opened lazily on the first query.
        $database = DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'Refusing to run tests against database [%s]: its name must end in "_test".',
                $database,
            ));
        }

        return $app;
    }
}
