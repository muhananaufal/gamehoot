<?php

declare(strict_types=1);

use App\Actions\Events\CloseEvent;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\OpenEvent;
use App\Actions\Events\ReopenEvent;
use App\Actions\Events\RestoreEvent;
use App\Actions\Events\SetJoinLock;
use App\Actions\Events\UpdateEventSettings;
use App\Actions\People\ClaimName;
use App\Actions\People\EditNameList;
use App\Actions\People\ReleaseClaim;
use App\Enums\Audience;
use App\Enums\EventStatus;
use App\Enums\ScreenTheme;
use App\Models\Event;
use App\Models\Person;
use App\Realtime\StateChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as Events;

uses(RefreshDatabase::class);

/**
 * F15, F22: every action that changes what a screen shows bumps state_version and
 * broadcasts the new snapshot on both channels.
 */
it('broadcasts the new version after each state change', function (Closure $change, string $status, int $joined): void {
    $event = Event::factory()->create(['slug' => 'gathering-2026', 'status' => $status, 'state_version' => 4]);
    $person = Person::factory()->for($event)->create();
    Person::factory()->for($event)->claimed()->create();
    $host = $event->owner()->firstOrFail();

    Events::fake([StateChanged::class]);
    $change($event, $person, $host);

    Events::assertDispatchedTimes(StateChanged::class, 2);
    Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => $sent->audience === Audience::Public
        && $sent->snapshot['version'] === 5
        && $sent->snapshot['lobby']['joined'] === $joined);
})->with([
    'open' => [fn (Event $e) => app(OpenEvent::class)->handle($e), 'draft', 1],
    'close' => [fn (Event $e, Person $p, $h) => app(CloseEvent::class)->handle($e, $h), 'open', 1],
    'reopen' => [fn (Event $e, Person $p, $h) => app(ReopenEvent::class)->handle($e, $h), 'finished', 1],
    'delete' => [fn (Event $e, Person $p, $h) => app(DeleteEvent::class)->handle($e, $h), 'open', 1],
    'restore' => [function (Event $e, Person $p, $h): void {
        $e->delete();
        app(RestoreEvent::class)->handle($e, 'gathering-2026', $h);
    }, 'open', 1],
    'settings' => [fn (Event $e) => app(UpdateEventSettings::class)->handle($e, 'New name', 'new-name', ScreenTheme::Light, true), 'open', 1],
    'join lock' => [fn (Event $e, Person $p, $h) => app(SetJoinLock::class)->handle($e, true, $h), 'open', 1],
    'add name' => [fn (Event $e) => app(EditNameList::class)->add($e, 'Rita Wulandari'), 'open', 1],
    'rename' => [fn (Event $e, Person $p) => app(EditNameList::class)->rename($e, $p, 'Rita'), 'open', 1],
    'delete name' => [fn (Event $e, Person $p) => app(EditNameList::class)->delete($e, $p), 'open', 1],
    'import' => [fn (Event $e) => app(EditNameList::class)->import($e, ['Ana', 'Budi']), 'open', 1],
    'claim (F23)' => [fn (Event $e, Person $p) => app(ClaimName::class)->handle($e, $p), 'open', 2],
    'release claim (F23)' => [fn (Event $e, Person $p, $h) => app(ReleaseClaim::class)->handle($e, $e->people()->whereNotNull('claimed_at')->firstOrFail(), $h), 'open', 0],
]);

it('sends nothing when an action changes nothing', function (): void {
    $event = Event::factory()->create(['status' => EventStatus::Open]);
    $person = Person::factory()->for($event)->create();

    Events::fake([StateChanged::class]);
    app(OpenEvent::class)->handle($event);
    app(ReleaseClaim::class)->handle($event, $person, $event->owner()->firstOrFail());

    Events::assertNotDispatched(StateChanged::class);
    expect($event->fresh()?->state_version)->toBe(0);
});
