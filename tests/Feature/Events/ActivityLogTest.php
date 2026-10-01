<?php

declare(strict_types=1);

use App\Enums\LoggedAction;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Pentahoot;
use Tests\Support\TebakGambar;

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
        ->assertSee('Important actions, newest first.')
        ->assertSeeInOrder(['Added a co-host', 'Dina (HR)', 'Deleted a name', 'Budi Santoso']);
});
