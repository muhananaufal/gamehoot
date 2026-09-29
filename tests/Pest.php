<?php

declare(strict_types=1);

use Tests\TestCase;

/*
| Feature tests boot the Laravel application. Database state is reset per
| test file with RefreshDatabase where a test needs it (W13: MySQL, never SQLite).
*/
pest()->extend(TestCase::class)->in('Feature');
