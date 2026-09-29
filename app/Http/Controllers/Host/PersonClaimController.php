<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\People\RegenerateJoinLink;
use App\Actions\People\ReleaseClaim;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * B-6: release a claimed name. T5: replace a leaked personal link. store = release, update = new link.
 */
final class PersonClaimController
{
    public function store(Event $event, Person $person, ReleaseClaim $release, #[CurrentUser] User $user): RedirectResponse
    {
        $release->handle($event, $person, $user);

        return redirect()->route('host.events.people.index', $event)->with('status', __('people.released', ['name' => $person->name]));
    }

    public function update(Event $event, Person $person, RegenerateJoinLink $regenerate): RedirectResponse
    {
        $regenerate->handle($person);

        return redirect()->route('host.events.people.index', $event)->with('status', __('people.link_regenerated', ['name' => $person->name]));
    }
}
