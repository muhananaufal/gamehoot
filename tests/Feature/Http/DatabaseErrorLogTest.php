<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Person;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as Events;

uses(RefreshDatabase::class);

/**
 * K5 (audit SEC-9): a failed query is logged without the values it carried. MySQL repeats the
 * clashing value in its own message ("Duplicate entry '...'"), and Laravel adds the bindings
 * to the SQL unless the connection masks them.
 */
it('logs a failed query without participant names or personal link tokens', function (): void {
    $event = Event::factory()->create();
    $person = Person::factory()->for($event)->create([
        'name' => 'Zakaria Secretname',
        'name_normalized' => 'zakaria secretname',
        'join_token' => 'TOKENsecret12345',
    ]);

    $lines = [];
    Events::listen(MessageLogged::class, function (MessageLogged $logged) use (&$lines): void {
        $lines[] = $logged->message.' '.json_encode($logged->context, JSON_PARTIAL_OUTPUT_ON_ERROR).' '
            .(($logged->context['exception'] ?? null) instanceof Throwable ? $logged->context['exception']->getMessage() : '');
    });

    try {
        DB::table('people')->insert([
            'id' => (string) Str::uuid7(),
            'event_id' => $event->id,
            'name' => $person->name,
            'name_normalized' => $person->name_normalized,
            'join_token' => 'TOKENsecret67890',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } catch (QueryException $exception) {
        report($exception);
    }

    $logged = implode("\n", $lines);

    expect($lines)->not->toBeEmpty()
        ->and($logged)->not->toContain('Secretname')
        ->and($logged)->not->toContain('secretname')
        ->and($logged)->not->toContain('TOKENsecret')
        // What is needed to debug stays: the statement and the index that refused it.
        ->and($logged)->toContain('insert into')
        ->and($logged)->toContain('people_event_id_name_normalized_unique');
});
