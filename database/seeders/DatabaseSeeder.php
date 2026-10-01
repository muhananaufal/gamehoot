<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * O10: only base data that is safe to run again in every environment. Pentahoot has none yet:
 * accounts come from `php artisan pentahoot:create-super-admin` (asked at the prompt), and
 * demo or load-test data from DemoSeeder and LoadTestSeeder, which refuse production.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Nothing to seed yet; kept so `db:seed` and deploy scripts have a safe entry point.
    }
}
