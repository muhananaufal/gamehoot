<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

/**
 * O17 (audit OPS-4): GET /up is what the deploy smoke test (P4) and the container healthcheck ask.
 * It only said "the app boots"; now it also asks the database, so a deploy against a database
 * that does not answer stops instead of going live.
 */
it('answers /up when the database answers (O17)', function (): void {
    getJson('/up')->assertOk()->assertExactJson(['status' => 'up']);
});

it('gives up connecting to the database within seconds (O17)', function (): void {
    $options = config('database.connections.mysql.options');

    expect($options)->toBeArray()->toHaveKey(PDO::ATTR_TIMEOUT)
        ->and(is_array($options) ? $options[PDO::ATTR_TIMEOUT] : null)->toBeLessThanOrEqual(5);
});

it('fails /up when the database does not answer (O17)', function (): void {
    config(['app.debug' => false, 'database.connections.mysql.port' => 1]);
    DB::purge('mysql');

    getJson('/up')->assertStatus(500)->assertExactJson(['status' => 'down']);
});
