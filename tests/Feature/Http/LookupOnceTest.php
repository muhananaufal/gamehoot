<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\People\ClaimCookie;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;
use function Pest\Laravel\withCredentials;

uses(RefreshDatabase::class);

/**
 * Audit CODE-2: a model a request names is looked up once, where it is validated, and the
 * controller uses that same model.
 *
 * @return Closure(): list<string>
 */
function recordQueries(): Closure
{
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    return function () use (&$queries): array {
        return $queries;
    };
}

it('looks up the account a co-host is added by once (C-2, audit CODE-2)', function (): void {
    $event = Event::factory()->create();
    User::factory()->create(['email' => 'arif@company.test']);
    $owner = $event->owner()->firstOrFail();

    $queries = recordQueries();
    actingAs($owner)->post("/host/{$event->id}/cohosts", ['email' => 'arif@company.test'])->assertSessionHasNoErrors();

    $lookups = array_filter($queries(), fn (string $sql): bool => str_contains($sql, 'from `users` where `email` = ?'));
    expect($lookups)->toHaveCount(1);
});

it('reads the name a phone votes for once (F3, audit CODE-2)', function (): void {
    travelTo(CarbonImmutable::parse('2026-10-15 10:00:00.000'));
    $event = Pentahoot::event(['Rita Wulandari', 'Budi Santoso', 'Ana Putri']);
    $question = Pentahoot::question(Pentahoot::running($event));
    actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/start")->assertNoContent();
    auth()->logout();

    $token = ClaimCookie::newToken();
    Pentahoot::person($event, 'Rita Wulandari')->forceFill(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()])->save();
    $ana = Pentahoot::person($event, 'Ana Putri');

    $queries = recordQueries();
    withCredentials()->withCookie(ClaimCookie::name($event), $token)
        ->postJson("/{$event->slug}/questions/{$question->id}/vote", ['target' => $ana->id, 'attempt' => 1])
        ->assertCreated()
        ->assertJsonPath('target.name', 'Ana Putri');

    // One read for the voter (the claim cookie), one for the target.
    $people = array_filter($queries(), fn (string $sql): bool => str_contains($sql, 'from `people`'));
    expect($people)->toHaveCount(2);
});

it('still refuses a name of another event as not on this list (F3)', function (): void {
    travelTo(CarbonImmutable::parse('2026-10-15 10:00:00.000'));
    $event = Pentahoot::event(['Rita Wulandari', 'Ana Putri']);
    $budi = Person::factory()->for(Event::factory())->create();
    $question = Pentahoot::question(Pentahoot::running($event));
    actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/start")->assertNoContent();
    auth()->logout();

    $token = ClaimCookie::newToken();
    Pentahoot::person($event, 'Rita Wulandari')->forceFill(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()])->save();

    withCredentials()->withCookie(ClaimCookie::name($event), $token)
        ->postJson("/{$event->slug}/questions/{$question->id}/vote", ['target' => $budi->id, 'attempt' => 1])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('target');
});
