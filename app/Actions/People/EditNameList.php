<?php

declare(strict_types=1);

namespace App\Actions\People;

use App\Exceptions\NameListLocked;
use App\Models\Event;
use App\Models\Person;
use App\People\JoinToken;
use App\People\PersonName;
use App\Realtime\StatePublisher;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * B-3, B-4: changes to the master name list. The event row is locked first so the check
 * against names_locked_at cannot race with a game starting. Duplicates are refused by
 * validation and, as the last guard, by the unique index (G2).
 */
final readonly class EditNameList
{
    public function __construct(private StatePublisher $publisher) {}

    public function add(Event $event, string $name): Person
    {
        return $this->locked($event, function (Event $locked) use ($name): Person {
            $person = $this->newPerson($name);
            $person->event()->associate($locked)->save();

            return $person;
        });
    }

    /**
     * @param  list<string>  $names
     */
    public function import(Event $event, array $names): void
    {
        $this->locked($event, function (Event $locked) use ($names): void {
            foreach ($names as $name) {
                $this->newPerson($name)->event()->associate($locked)->save();
            }
        });
    }

    public function rename(Event $event, Person $person, string $name): void
    {
        $this->locked($event, function () use ($person, $name): void {
            $person->forceFill([
                'name' => PersonName::display($name),
                'name_normalized' => PersonName::normalize($name),
            ])->save();
        });
    }

    public function delete(Event $event, Person $person): void
    {
        $this->locked($event, fn () => $person->delete());
    }

    /**
     * @template T
     *
     * @param  Closure(Event): T  $change
     * @return T
     *
     * @throws NameListLocked
     */
    private function locked(Event $event, Closure $change): mixed
    {
        return DB::transaction(function () use ($event, $change): mixed {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->names_locked_at !== null) {
                throw NameListLocked::make();
            }

            $result = $change($locked);
            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);

            return $result;
        });
    }

    private function newPerson(string $name): Person
    {
        $person = new Person;
        $person->forceFill([
            'name' => PersonName::display($name),
            'name_normalized' => PersonName::normalize($name),
            'join_token' => JoinToken::generate(),
        ]);

        return $person;
    }
}
