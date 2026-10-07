<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Actions\Games\CreateGame;
use App\Actions\People\EditNameList;
use App\Enums\ScreenTheme;
use App\Models\Event;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * U26: New event saves its event, names and games together, at its last step. One transaction:
 * if any part fails, nothing is saved. The actions are the ones the pages use one by one.
 */
final readonly class SetUpEvent
{
    public function __construct(
        private CreateEvent $createEvent,
        private EditNameList $names,
        private CreateGame $createGame,
    ) {}

    /**
     * @param  list<string>  $names
     * @param  list<QuestionPack>  $packs  in the order picked
     */
    public function handle(User $owner, string $name, string $slug, ScreenTheme $theme, bool $showOnDevices, array $names, array $packs): Event
    {
        return DB::transaction(function () use ($owner, $name, $slug, $theme, $showOnDevices, $names, $packs): Event {
            $event = $this->createEvent->handle($owner, $name, $slug, $theme, $showOnDevices);

            if ($names !== []) {
                $this->names->import($event, $names, $owner);
            }

            if ($packs !== []) {
                $this->createGame->handleMany($event, $packs);
            }

            return $event;
        });
    }
}
