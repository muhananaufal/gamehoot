<?php

declare(strict_types=1);

namespace App\Http\Controllers\Join;

use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Person;
use App\People\ClaimCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F9: claimed phones download the whole name list once and search it without asking the
 * server on every keystroke.
 */
final class PeopleController
{
    public function __invoke(Request $request, Event $event): JsonResponse
    {
        if (ClaimCookie::person($request, $event) === null) {
            throw ActionRefused::notClaimed();
        }

        return response()->json([
            'people' => $event->people()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Person $person): array => ['id' => $person->id, 'name' => $person->name])
                ->all(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
