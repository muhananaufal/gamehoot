<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * C-2: co-hosts equal the owner except for deleting, managing co-hosts and transferring.
 * C-4: a super-admin has no access to other hosts' events unless added as co-host, but may
 * restore any deleted event.
 */
final class EventPolicy
{
    public function view(User $user, Event $event): bool
    {
        return $event->isHostedBy($user);
    }

    public function update(User $user, Event $event): bool
    {
        return $event->isHostedBy($user);
    }

    public function close(User $user, Event $event): bool
    {
        return $event->isHostedBy($user);
    }

    public function lockJoining(User $user, Event $event): bool
    {
        return $event->isHostedBy($user);
    }

    public function reopen(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function manageCohosts(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function transfer(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function delete(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user);
    }

    public function restore(User $user, Event $event): bool
    {
        return $event->isOwnedBy($user) || $user->is_super_admin;
    }
}
