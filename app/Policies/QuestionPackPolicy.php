<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QuestionPack;
use App\Models\User;

/**
 * D-9: in phase 1 a pack is used by its owner only.
 */
final class QuestionPackPolicy
{
    public function view(User $user, QuestionPack $pack): bool
    {
        return $pack->owner_id === $user->id;
    }

    public function update(User $user, QuestionPack $pack): bool
    {
        return $pack->owner_id === $user->id;
    }

    public function delete(User $user, QuestionPack $pack): bool
    {
        return $pack->owner_id === $user->id;
    }
}
