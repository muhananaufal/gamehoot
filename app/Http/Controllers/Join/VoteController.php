<?php

declare(strict_types=1);

namespace App\Http\Controllers\Join;

use App\Actions\Votes\CastVote;
use App\Exceptions\ActionRefused;
use App\Http\Requests\Join\VoteRequest;
use App\Models\Event;
use App\Models\Question;
use App\People\ClaimCookie;
use Illuminate\Http\JsonResponse;

/**
 * F3, F11: a phone votes with a plain POST, limited per claim token.
 */
final class VoteController
{
    public function __invoke(VoteRequest $request, Event $event, Question $question, CastVote $castVote): JsonResponse
    {
        abort_unless($question->game()->where('event_id', $event->id)->exists(), 404);

        $voter = ClaimCookie::person($request, $event) ?? throw ActionRefused::notClaimed();
        $target = $request->target();

        $castVote->handle($event, $question, $voter, $target, $request->integer('attempt'));

        // E4: the phone shows the name it sent.
        return response()->json(['target' => ['id' => $target->id, 'name' => $target->name]], 201);
    }
}
