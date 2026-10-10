<?php

declare(strict_types=1);

use App\Actions\Events\SetJoinLock;
use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Pentahoot;
use Tests\Support\TebakGambar;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

/**
 * The single log entry of this action, with its payload.
 */
function loggedOnce(LoggedAction $action): ActionLog
{
    return ActionLog::query()->where('action', $action)->sole();
}

function hostOfEvent(Event $event): User
{
    return $event->owner()->firstOrFail();
}

describe('E13: every action that cannot be undone is logged', function (): void {
    it('logs opening the event and starting a game, which locks the name list', function (): void {
        $event = Event::factory()->for(User::factory(), 'owner')->create();
        Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);
        $game = Pentahoot::game($event);
        $host = hostOfEvent($event);

        actingAs($host)->post("/host/{$event->id}/open")->assertRedirect();
        actingAs($host)->post("/host/{$event->id}/games/{$game->id}/start")->assertRedirect();

        expect(loggedOnce(LoggedAction::EventOpened)->user_id)->toBe($host->id)
            ->and(loggedOnce(LoggedAction::GameStarted)->payload)->toBe(['game_id' => $game->id]);
    });

    it('logs a Pentahoot reveal, which freezes the result', function (): void {
        travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
        $event = Pentahoot::event();
        $question = Pentahoot::question(Pentahoot::running($event));
        actingAs(hostOfEvent($event))->postJson("/host/{$event->id}/questions/{$question->id}/start")->assertNoContent();
        travelTo(now()->addSeconds(30));

        actingAs(hostOfEvent($event))->postJson("/host/{$event->id}/questions/{$question->id}/reveal")->assertNoContent();

        expect(loggedOnce(LoggedAction::QuestionRevealed)->payload)->toBe(['question_id' => $question->id]);
    });

    it('logs a Tebak Gambar reveal, which turns Skip off', function (): void {
        Storage::fake('media');
        $event = Pentahoot::event();
        $question = TebakGambar::running($event)->questions()->orderBy('position')->firstOrFail();
        actingAs(hostOfEvent($event))->postJson("/host/{$event->id}/questions/{$question->id}/show")->assertNoContent();

        actingAs(hostOfEvent($event))->postJson("/host/{$event->id}/questions/{$question->id}/reveal")->assertNoContent();

        expect(loggedOnce(LoggedAction::QuestionRevealed)->payload)->toBe(['question_id' => $question->id]);
    });

    it('logs a new personal link, which kills the old one (T5)', function (): void {
        $event = Pentahoot::event(['Rita Wulandari']);
        $rita = Pentahoot::person($event, 'Rita Wulandari');

        actingAs(hostOfEvent($event))->post("/host/{$event->id}/people/{$rita->id}/regenerate-link")->assertRedirect();

        expect(loggedOnce(LoggedAction::LinkRegenerated)->payload)->toBe(['person_id' => $rita->id]);
    });

    it('logs deleting a name and a game, keeping what they were called', function (): void {
        $event = Event::factory()->for(User::factory(), 'owner')->open()->create();
        $budi = Person::factory()->for($event)->create(['name' => 'Budi Santoso']);
        $game = Pentahoot::game($event, title: 'Office Awards');
        $host = hostOfEvent($event);

        actingAs($host)->delete("/host/{$event->id}/people/{$budi->id}")->assertRedirect();
        actingAs($host)->delete("/host/{$event->id}/games/{$game->id}")->assertRedirect();

        expect(loggedOnce(LoggedAction::PersonDeleted)->payload)->toBe(['name' => 'Budi Santoso'])
            ->and(loggedOnce(LoggedAction::GameDeleted)->payload)->toBe(['title' => 'Office Awards']);
    });

    it('logs who gained or lost co-host access', function (): void {
        $event = Event::factory()->for(User::factory(), 'owner')->create();
        $dina = User::factory()->create(['name' => 'Dina', 'email' => 'dina@company.test']);
        $host = hostOfEvent($event);

        actingAs($host)->post("/host/{$event->id}/cohosts", ['email' => 'dina@company.test'])->assertRedirect();
        actingAs($host)->delete("/host/{$event->id}/cohosts/{$dina->id}")->assertRedirect();

        expect(loggedOnce(LoggedAction::CohostAdded)->payload)->toBe(['account_id' => $dina->id])
            ->and(loggedOnce(LoggedAction::CohostRemoved)->payload)->toBe(['account_id' => $dina->id]);
    });

    it('logs a changed event link, which old QR codes and phones still use (F10)', function (): void {
        $event = Event::factory()->for(User::factory(), 'owner')->create(['name' => 'Gathering', 'slug' => 'gathering']);
        $host = hostOfEvent($event);

        actingAs($host)->put("/host/{$event->id}/settings", ['name' => 'Gathering', 'slug' => 'gathering', 'screen_theme' => 'dark'])->assertRedirect();
        expect(ActionLog::query()->count())->toBe(0);

        actingAs($host)->put("/host/{$event->id}/settings", ['name' => 'Gathering', 'slug' => 'gathering-2026', 'screen_theme' => 'dark'])->assertRedirect();

        // MySQL stores JSON keys in its own order.
        expect(loggedOnce(LoggedAction::LinkChanged)->payload)->toEqual(['from' => 'gathering', 'to' => 'gathering-2026']);
    });
});

it('shows the new entries in words on the Activity log page', function (): void {
    $event = Event::factory()->for(User::factory(), 'owner')->open()->create();
    $budi = Person::factory()->for($event)->create(['name' => 'Budi Santoso']);
    $dina = User::factory()->create(['name' => 'Dina (HR)', 'email' => 'dina@company.test']);
    $host = hostOfEvent($event);
    actingAs($host)->delete("/host/{$event->id}/people/{$budi->id}");
    actingAs($host)->post("/host/{$event->id}/cohosts", ['email' => 'dina@company.test']);

    actingAs($host)->get("/host/{$event->id}/logs")
        ->assertOk()
        ->assertDontSee('Important actions, newest first.')
        ->assertSeeInOrder(['Added a co-host', 'Dina (HR)', 'Deleted a name', 'Budi Santoso']);
});

it('lets the host search the log and sort it by time in the browser (U4)', function (): void {
    $event = Event::factory()->open()->create();
    $host = hostOfEvent($event);
    app(SetJoinLock::class)->handle($event, true, $host);

    $html = (string) actingAs($host)->get("/host/{$event->id}/logs")->assertOk()->getContent();

    expect($html)->toContain("x-data=\"tableView({ key: 'logs:{$event->id}', sort: 'at', dir: 'desc' })\"")
        ->and($html)->toContain('Rows per page')
        ->and($html)->toContain('placeholder="Search action or item"')
        ->and($html)->toMatch('/<tr data-row data-search="[^"]*locked new name claims[^"]*" data-sort-at="\d+"/');
});

it('filters the log by who did it and by date, and sorts every column (U4)', function (): void {
    $owner = User::factory()->create(['name' => 'Owner Ana']);
    $event = Event::factory()->for($owner, 'owner')->open()->create();
    $cohost = User::factory()->create(['name' => 'Co Budi']);
    $event->cohosts()->attach($cohost);
    travelTo(CarbonImmutable::parse('2026-10-04 18:30:00'));
    app(SetJoinLock::class)->handle($event, true, $cohost);

    $html = (string) actingAs($owner)->get("/host/{$event->id}/logs")->assertOk()->getContent();

    // Owner first, then the co-hosts: everyone who can act on the event.
    // U22: the Who dropdown lists Everyone, the owner, then the co-hosts.
    expect($html)->toMatch('/selectBox\(\{ options: [^\n]*Everyone[^\n]*Owner Ana[^\n]*Co Budi[\s\S]*?x-model="who"/')
        ->and($html)->toContain('data-who="'.$cohost->id.'"')
        // 18:30 UTC on 4 Oct is 5 Oct in WIB (T10).
        ->and($html)->toContain('data-date="2026-10-05"')
        ->and($html)->toContain('dateRangePicker');

    foreach (['at', 'who', 'what'] as $key) {
        expect($html)->toContain("sortBy('{$key}')")->toContain("data-sort-{$key}=");
    }
});

it('puts the Who filter beside Rows per page, before the date and the search (U23)', function (): void {
    $event = Event::factory()->open()->create();
    $host = hostOfEvent($event);
    app(SetJoinLock::class)->handle($event, true, $host);

    $html = (string) actingAs($host)->get("/host/{$event->id}/logs")->assertOk()->getContent();

    expect(strpos($html, 'aria-label="Who"'))->toBeLessThan((int) strpos($html, 'Reset filters'));
});

describe('E13: skips, reloads, deleted questions, finished games and added names (audit 7 October 2026)', function (): void {
    it('logs a Tebak skip, which uses the one skip of the question', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::running($event, [['A?', 'AB'], ['B?', 'CD']]);
        $first = $game->questions()->where('position', 1)->firstOrFail();
        actingAs(hostOfEvent($event))->postJson("/host/{$event->id}/questions/{$first->id}/show")->assertNoContent();

        actingAs(hostOfEvent($event))->postJson("/host/{$event->id}/questions/{$first->id}/skip")->assertNoContent();

        expect(loggedOnce(LoggedAction::QuestionSkipped)->payload)->toBe(['question_id' => $first->id]);
    });

    it('logs Reload from pack, which replaces the edits of the copies', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack(hostOfEvent($event));
        $game = Pentahoot::game($event);
        $game->sourcePack()->associate($pack)->save();

        actingAs(hostOfEvent($event))->post("/host/{$event->id}/games/{$game->id}/reload")->assertRedirect();

        expect(loggedOnce(LoggedAction::GameReloaded)->payload)->toBe(['game_id' => $game->id]);
    });

    it('logs a deleted question with its text, so the entry stays readable', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::game($event, ['Most punctual?', 'Best presenter?']);
        $question = Pentahoot::question($game, 2);

        actingAs(hostOfEvent($event))->delete("/host/{$event->id}/games/{$game->id}/questions/{$question->id}")->assertRedirect();

        // The JSON column keeps its own key order.
        expect(loggedOnce(LoggedAction::QuestionDeleted)->payload)->toEqual(['game_id' => $game->id, 'title' => 'Best presenter?']);
    });

    it('logs a game finished after its last question, apart from an early finish', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event);
        $game->questions()->update(['status' => QuestionStatus::Done]);

        actingAs(hostOfEvent($event))->post("/host/{$event->id}/games/{$game->id}/finish", ['version' => $event->fresh()?->state_version])->assertRedirect();

        expect(loggedOnce(LoggedAction::GameFinished)->payload)->toBe(['game_id' => $game->id])
            ->and(ActionLog::query()->where('action', LoggedAction::GameFinishedEarly)->exists())->toBeFalse();
    });

    it('logs names added by hand or by import with their count only', function (): void {
        $event = Event::factory()->for(User::factory(), 'owner')->create();
        $host = hostOfEvent($event);

        actingAs($host)->post("/host/{$event->id}/people", ['names' => implode(PHP_EOL, ['Rita Wulandari', 'Budi Santoso'])])->assertRedirect();
        actingAs($host)->post("/host/{$event->id}/people/import/confirm", ['names' => ['Ana Putri']])->assertRedirect();

        expect(ActionLog::query()->where('action', LoggedAction::NamesAdded)->orderBy('id')->pluck('payload')->all())
            ->toBe([['count' => 2], ['count' => 1]]);
    });

    it('shows the new entries in the words of the page', function (): void {
        $event = Event::factory()->for(User::factory(), 'owner')->create();
        actingAs(hostOfEvent($event))->post("/host/{$event->id}/people", ['names' => implode(PHP_EOL, ['Rita Wulandari', 'Budi Santoso'])]);

        actingAs(hostOfEvent($event))->get("/host/{$event->id}/logs")
            ->assertSee('Added names')
            ->assertSee('2 names');
    });
});
