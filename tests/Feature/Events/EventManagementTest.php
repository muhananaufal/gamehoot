<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Enums\LoggedAction;
use App\Enums\ScreenTheme;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function ownedEvent(?User $owner = null, array $attributes = []): Event
{
    return Event::factory()->for($owner ?? User::factory()->create(), 'owner')->create($attributes);
}

function ownerOf(Event $event): User
{
    return $event->owner()->firstOrFail();
}

function cohostOf(Event $event): User
{
    $cohost = User::factory()->create();
    $event->cohosts()->attach($cohost);

    return $cohost;
}

function logged(LoggedAction $action): int
{
    return ActionLog::query()->where('action', $action)->count();
}

describe('creating an event', function (): void {
    it('creates a draft event owned by the host, with the link made from the name', function (): void {
        $host = User::factory()->create();

        $response = actingAs($host)->post('/host/events', [
            'name' => 'Gathering 2026',
            'slug' => '',
            'screen_theme' => 'light',
        ]);

        $event = Event::query()->firstOrFail();
        $response->assertRedirect("/host/{$event->id}/settings");
        expect($event->owner_id)->toBe($host->id)
            ->and($event->slug)->toBe('gathering-2026')
            ->and($event->status)->toBe(EventStatus::Draft)
            ->and($event->screen_theme)->toBe(ScreenTheme::Light)
            ->and($event->show_on_devices)->toBeFalse();
    });

    it('rejects links reserved by the app (F10)', function (string $slug): void {
        actingAs(User::factory()->create())
            ->post('/host/events', ['name' => 'Anything', 'slug' => $slug, 'screen_theme' => 'dark'])
            ->assertSessionHasErrors('slug');
    })->with(['host', 'admin', 'login', 'logout', 'media', 'broadcasting', 'storage', 'build', 'up']);

    it('rejects a link that is taken or badly formed', function (string $slug): void {
        ownedEvent(attributes: ['slug' => 'gathering-2026']);

        actingAs(User::factory()->create())
            ->post('/host/events', ['name' => 'Anything', 'slug' => $slug, 'screen_theme' => 'dark'])
            ->assertSessionHasErrors('slug');
    })->with(['gathering-2026', 'Has Spaces', 'ab', 'ends-with-dash-', '--deleted-x']);

    it('shows the create form', function (): void {
        actingAs(User::factory()->create())->get('/host/events/create')
            ->assertOk()
            ->assertSee('Name and link');
    });
});

describe('who may open an event (C-2, C-4)', function (): void {
    it('lets the owner and co-hosts open the settings', function (): void {
        $event = ownedEvent(attributes: ['name' => 'Gathering 2026']);

        actingAs(ownerOf($event))->get("/host/{$event->id}/settings")->assertOk()->assertSee('Gathering 2026');
        actingAs(cohostOf($event))->get("/host/{$event->id}/settings")->assertOk();
    });

    it('keeps other hosts and super-admins out unless they are co-hosts', function (): void {
        $event = ownedEvent();

        actingAs(User::factory()->create())->get("/host/{$event->id}/settings")->assertForbidden();
        actingAs(User::factory()->superAdmin()->create())->get("/host/{$event->id}/settings")->assertForbidden();
    });
});

describe('settings', function (): void {
    it('updates name, link, theme and the phone toggle, and bumps the state version (F15)', function (): void {
        $event = ownedEvent();

        actingAs(cohostOf($event))->put("/host/{$event->id}/settings", [
            'name' => 'Year-End Party',
            'slug' => 'year-end',
            'screen_theme' => 'light',
            'show_on_devices' => '1',
        ])->assertRedirect("/host/{$event->id}/settings");

        $event->refresh();
        expect($event->name)->toBe('Year-End Party')
            ->and($event->slug)->toBe('year-end')
            ->and($event->screen_theme)->toBe(ScreenTheme::Light)
            ->and($event->show_on_devices)->toBeTrue()
            ->and($event->state_version)->toBe(1);
    });

    it('keeps its own link when saving without changing it', function (): void {
        $event = ownedEvent(attributes: ['slug' => 'keep-me']);

        actingAs(ownerOf($event))->put("/host/{$event->id}/settings", [
            'name' => 'Renamed',
            'slug' => 'keep-me',
            'screen_theme' => 'dark',
        ])->assertSessionHasNoErrors()->assertRedirect("/host/{$event->id}/settings");

        expect($event->refresh()->name)->toBe('Renamed')
            ->and($event->slug)->toBe('keep-me');
    });
});

describe('co-hosts (C-2)', function (): void {
    it('lets the owner add and remove a co-host by email', function (): void {
        $event = ownedEvent();
        $arif = User::factory()->create(['email' => 'arif@company.test']);

        actingAs(ownerOf($event))->post("/host/{$event->id}/cohosts", ['email' => 'ARIF@company.test'])
            ->assertRedirect("/host/{$event->id}/settings");
        expect($event->cohosts()->whereKey($arif->id)->exists())->toBeTrue();

        actingAs(ownerOf($event))->delete("/host/{$event->id}/cohosts/{$arif->id}");
        expect($event->cohosts()->count())->toBe(0);
    });

    it('rejects unknown, disabled, duplicate co-hosts and the owner', function (): void {
        $event = ownedEvent();
        $existing = cohostOf($event);
        User::factory()->disabled()->create(['email' => 'off@company.test']);

        foreach (['nobody@company.test', 'off@company.test', $existing->email, ownerOf($event)->email] as $email) {
            actingAs(ownerOf($event))->post("/host/{$event->id}/cohosts", ['email' => $email])
                ->assertSessionHasErrors('email');
        }
    });

    it('does not let a co-host manage co-hosts, transfer or delete', function (): void {
        $event = ownedEvent();
        $cohost = cohostOf($event);
        $other = User::factory()->create();

        actingAs($cohost)->post("/host/{$event->id}/cohosts", ['email' => $other->email])->assertForbidden();
        actingAs($cohost)->post("/host/{$event->id}/transfer", ['new_owner_email' => $other->email])->assertForbidden();
        actingAs($cohost)->delete("/host/{$event->id}", ['confirm_name' => $event->name])->assertForbidden();
    });
});

describe('transferring ownership (C-2)', function (): void {
    it('gives the event to another host, keeps the old owner as co-host and logs it', function (): void {
        $event = ownedEvent();
        $oldOwner = ownerOf($event);
        $sari = User::factory()->create(['email' => 'sari@company.test']);
        $event->cohosts()->attach($sari);

        actingAs($oldOwner)->post("/host/{$event->id}/transfer", ['new_owner_email' => 'sari@company.test'])
            ->assertRedirect("/host/{$event->id}/settings");

        $event->refresh();
        expect($event->owner_id)->toBe($sari->id)
            ->and($event->cohosts()->pluck('users.id')->all())->toBe([$oldOwner->id])
            ->and(logged(LoggedAction::OwnershipTransferred))->toBe(1);
    });
});

describe('deleting and restoring (D-3)', function (): void {
    it('needs the event name typed to confirm', function (): void {
        $event = ownedEvent(attributes: ['name' => 'Gathering 2026']);

        actingAs(ownerOf($event))->delete("/host/{$event->id}", ['confirm_name' => 'Gathering'])
            ->assertSessionHasErrors('confirm_name');

        expect($event->fresh()?->trashed())->toBeFalse();
    });

    it('soft deletes, frees the link, records who deleted it and logs it', function (): void {
        $event = ownedEvent(attributes: ['name' => 'Gathering 2026', 'slug' => 'gathering-2026']);
        $owner = ownerOf($event);

        actingAs($owner)->delete("/host/{$event->id}", ['confirm_name' => 'Gathering 2026'])
            ->assertRedirect('/host');

        $deleted = Event::withTrashed()->findOrFail($event->id);
        expect($deleted->trashed())->toBeTrue()
            ->and($deleted->slug)->toBe("gathering-2026--deleted-{$event->id}")
            ->and($deleted->deleted_by)->toBe($owner->id)
            ->and(logged(LoggedAction::EventDeleted))->toBe(1);

        actingAs($owner)->get('/host/trash')->assertOk()->assertSee('Gathering 2026')->assertSee('/gathering-2026');
    });

    it('restores with the original link and clears who deleted it', function (): void {
        $event = ownedEvent(attributes: ['name' => 'Gathering 2026', 'slug' => 'gathering-2026']);
        $owner = ownerOf($event);
        actingAs($owner)->delete("/host/{$event->id}", ['confirm_name' => 'Gathering 2026']);

        actingAs($owner)->post("/host/trash/{$event->id}/restore", ['slug' => 'gathering-2026'])
            ->assertRedirect("/host/{$event->id}/settings");

        $event->refresh();
        expect($event->trashed())->toBeFalse()
            ->and($event->slug)->toBe('gathering-2026')
            ->and($event->deleted_by)->toBeNull()
            ->and(logged(LoggedAction::EventRestored))->toBe(1);
    });

    it('asks for a new link when the original one was taken meanwhile', function (): void {
        $event = ownedEvent(attributes: ['name' => 'Gathering 2026', 'slug' => 'gathering-2026']);
        $owner = ownerOf($event);
        actingAs($owner)->delete("/host/{$event->id}", ['confirm_name' => 'Gathering 2026']);
        ownedEvent(attributes: ['slug' => 'gathering-2026']);

        actingAs($owner)->post("/host/trash/{$event->id}/restore", ['slug' => 'gathering-2026'])
            ->assertSessionHasErrors('slug');

        actingAs($owner)->post("/host/trash/{$event->id}/restore", ['slug' => 'gathering-2026-b'])
            ->assertSessionHasNoErrors();
        expect($event->fresh()?->slug)->toBe('gathering-2026-b');
    });

    it('lets a super-admin see and restore any deleted event, but not a co-host (C-2, C-4)', function (): void {
        $event = ownedEvent(attributes: ['name' => 'Sales Kickoff', 'slug' => 'sales-kickoff']);
        $cohost = cohostOf($event);
        actingAs(ownerOf($event))->delete("/host/{$event->id}", ['confirm_name' => 'Sales Kickoff']);

        actingAs($cohost)->post("/host/trash/{$event->id}/restore", ['slug' => 'sales-kickoff'])->assertForbidden();

        $admin = User::factory()->superAdmin()->create();
        actingAs($admin)->get('/admin/trash')->assertOk()->assertSee('Sales Kickoff');
        actingAs($admin)->post("/admin/trash/{$event->id}/restore", ['slug' => 'sales-kickoff'])
            ->assertRedirect('/admin/trash');
        expect($event->fresh()?->trashed())->toBeFalse();
    });

    it('keeps deleted events away from the dashboard and settings', function (): void {
        $event = ownedEvent(attributes: ['name' => 'Gone Event', 'slug' => 'gone-event']);
        $owner = ownerOf($event);
        actingAs($owner)->delete("/host/{$event->id}", ['confirm_name' => 'Gone Event']);

        // The flash message names the event once; the table must not list it.
        actingAs($owner)->get('/host')->assertSee('Gone Event moved to Deleted events.')->assertDontSee('gone-event');
        actingAs($owner)->get('/host')->assertDontSee('Gone Event');
        actingAs($owner)->get("/host/{$event->id}/settings")->assertNotFound();
    });
});

describe('closing and reopening (D-7)', function (): void {
    it('lets a co-host open a draft event so the links start working', function (): void {
        $event = ownedEvent();

        actingAs(cohostOf($event))->post("/host/{$event->id}/open")->assertRedirect();

        expect($event->refresh()->status)->toBe(EventStatus::Open)
            ->and($event->state_version)->toBe(1);
    });

    it('lets a co-host close an event and only the owner reopen it, logging both', function (): void {
        $event = ownedEvent();
        Event::query()->whereKey($event->id)->update(['status' => EventStatus::Open->value]);
        $cohost = cohostOf($event);

        actingAs($cohost)->post("/host/{$event->id}/close")->assertRedirect();
        expect($event->refresh()->status)->toBe(EventStatus::Finished);

        actingAs($cohost)->post("/host/{$event->id}/reopen")->assertForbidden();
        actingAs(ownerOf($event))->post("/host/{$event->id}/reopen");
        expect($event->refresh()->status)->toBe(EventStatus::Open)
            ->and(logged(LoggedAction::EventClosed))->toBe(1)
            ->and(logged(LoggedAction::EventReopened))->toBe(1);
    });

    it('refuses to close while a game is running (T6)', function (): void {
        $event = ownedEvent();
        $game = Game::factory()->for($event)->create();
        $event->forceFill(['status' => EventStatus::Open, 'active_game_id' => $game->id])->save();

        actingAs(ownerOf($event))->post("/host/{$event->id}/close")->assertSessionHasErrors('event');
        expect($event->refresh()->status)->toBe(EventStatus::Open);
    });
});

describe('join lock (B-7)', function (): void {
    it('locks and unlocks new name claims and logs both', function (): void {
        $event = ownedEvent();

        actingAs(cohostOf($event))->post("/host/{$event->id}/join-lock", ['locked' => '1']);
        expect($event->refresh()->join_locked_at)->not->toBeNull();

        actingAs(ownerOf($event))->post("/host/{$event->id}/join-lock", ['locked' => '0']);
        expect($event->refresh()->join_locked_at)->toBeNull()
            ->and(logged(LoggedAction::JoinLocked))->toBe(1)
            ->and(logged(LoggedAction::JoinUnlocked))->toBe(1);
    });
});

it('is closed to guests', function (): void {
    $event = ownedEvent();

    get("/host/{$event->id}/settings")->assertRedirect('/login');
});
