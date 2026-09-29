<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Models\Person;
use App\People\JoinToken;

/**
 * T5: a leaked personal link stops working once a new one is made. A phone that already
 * claimed the name keeps its claim cookie.
 */
final class RegenerateJoinLink
{
    public function handle(Person $person): void
    {
        $person->forceFill(['join_token' => JoinToken::generate()])->save();
    }
}
