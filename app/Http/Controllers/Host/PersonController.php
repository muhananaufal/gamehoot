<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\People\EditNameList;
use App\Exceptions\NameListLocked;
use App\Http\Requests\Host\PersonNameRequest;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Closure;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * B-3, B-4: the master name list of an event.
 */
final class PersonController
{
    public function __construct(private readonly EditNameList $names) {}

    public function index(Request $request, Event $event): View
    {
        // U4: every name is sent; search, filter and sort happen in the browser.
        $people = $event->people()->orderBy('name')->get();

        return view('host.people.index', [
            'event' => $event,
            'people' => $people,
            'counts' => [
                'all' => $people->count(),
                'joined' => $people->whereNotNull('claimed_at')->count(),
                'waiting' => $people->whereNull('claimed_at')->count(),
            ],
            'editing' => $request->string('edit')->toString(),
        ]);
    }

    public function store(PersonNameRequest $request, Event $event): RedirectResponse
    {
        return $this->change($event, fn () => $this->names->add($event, $request->string('name')->toString()), 'people.added');
    }

    public function update(PersonNameRequest $request, Event $event, Person $person): RedirectResponse
    {
        return $this->change($event, fn () => $this->names->rename($event, $person, $request->string('name')->toString()), 'people.renamed');
    }

    public function destroy(Event $event, Person $person, #[CurrentUser] User $user): RedirectResponse
    {
        return $this->change($event, fn () => $this->names->delete($event, $person, $user), 'people.deleted');
    }

    private function change(Event $event, Closure $change, string $message): RedirectResponse
    {
        try {
            $change();
        } catch (NameListLocked) {
            return back()->withErrors(['names' => __('people.locked')]);
        } catch (UniqueConstraintViolationException) {
            // Another host saved the same name a moment earlier (G2 is the last guard).
            return back()->withInput()->withErrors(['name' => __('people.duplicate_race')]);
        }

        return redirect()->route('host.events.people.index', $event)->with('status', __($message));
    }
}
