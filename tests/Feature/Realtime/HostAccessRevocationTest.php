<?php

declare(strict_types=1);

use App\Actions\Accounts\DeleteAccount;
use App\Actions\Accounts\SetAccountStatus;
use App\Actions\Events\ManageCohosts;
use App\Actions\Events\OpenEvent;
use App\Actions\Events\TransferOwnership;
use App\Enums\Audience;
use App\Models\Event;
use App\Models\User;
use App\Realtime\HostChannelRotated;
use App\Realtime\StateChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as Events;

uses(RefreshDatabase::class);

/**
 * F26 (audit SEC-1): channel authorization runs only when a socket joins. Whoever loses access
 * must stop receiving host data on the socket that is already open, so the key changes and the
 * old channel only hears "rotated".
 */
describe('host channel key rotation (F26)', function (): void {
    it('gives the host channel a new key when a co-host is removed, and sends host data there only', function (): void {
        $event = Event::factory()->create();
        $owner = $event->owner()->firstOrFail();
        $cohost = User::factory()->create();
        $event->cohosts()->attach($cohost);
        $old = Audience::Host->channelName($event);
        Events::fake([HostChannelRotated::class, StateChanged::class]);

        app(ManageCohosts::class)->remove($event, $cohost, $owner);

        $event->refresh();
        $new = Audience::Host->channelName($event);
        expect($new)->not->toBe($old);
        Events::assertDispatched(HostChannelRotated::class, fn (HostChannelRotated $sent): bool => $sent->broadcastOn()->name === "private-{$old}");

        app(OpenEvent::class)->handle($event, $owner);
        Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => $sent->audience === Audience::Host && $sent->broadcastOn()->name === "private-{$new}");
    });

    it('rotates every event a disabled account owns or co-hosts, and leaves others alone', function (): void {
        $account = User::factory()->create();
        $owned = Event::factory()->for($account, 'owner')->create();
        $cohosted = Event::factory()->create();
        $cohosted->cohosts()->attach($account);
        $other = Event::factory()->create();
        $keys = fn (): array => [$owned->fresh()?->hostChannelKey(), $cohosted->fresh()?->hostChannelKey(), $other->fresh()?->hostChannelKey()];
        $before = $keys();

        app(SetAccountStatus::class)->handle($account, true, User::factory()->create());

        [$ownedKey, $cohostedKey, $otherKey] = $keys();
        expect($ownedKey)->not->toBe($before[0])
            ->and($cohostedKey)->not->toBe($before[1])
            ->and($otherKey)->toBe($before[2]);
    });

    it('rotates the events a deleted account co-hosted', function (): void {
        $account = User::factory()->create();
        $event = Event::factory()->create();
        $event->cohosts()->attach($account);
        $before = $event->hostChannelKey();

        app(DeleteAccount::class)->handle($account, User::factory()->create());

        expect($event->fresh()?->hostChannelKey())->not->toBe($before);
    });

    it('rotates the key when ownership moves to another host', function (): void {
        $event = Event::factory()->create();
        $before = $event->hostChannelKey();

        app(TransferOwnership::class)->handle($event, User::factory()->create(), $event->owner()->firstOrFail());

        expect($event->fresh()?->hostChannelKey())->not->toBe($before);
    });

    it('never puts the key into a serialized event', function (): void {
        $event = Event::factory()->create();

        expect($event->toArray())->not->toHaveKey('host_channel_key')
            ->and(strlen($event->hostChannelKey()))->toBe(32);
    });
});
