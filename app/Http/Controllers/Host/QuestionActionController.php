<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\RunQuestionAction;
use App\Exceptions\ActionRefused;
use App\Games\GameEngines;
use App\Http\Requests\Host\QuestionActionRequest;
use App\Models\Event;
use App\Models\Question;
use App\Models\User;
use App\Support\SeenVersion;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

/**
 * F13: one route for every question action; the engine of the game type decides which
 * actions exist and whether the state allows them (F6, C-3). Called by Live control with fetch.
 */
final class QuestionActionController
{
    public function __invoke(QuestionActionRequest $request, Event $event, Question $question, string $action, GameEngines $engines, RunQuestionAction $run, #[CurrentUser] User $user): Response
    {
        $game = $question->game()->where('event_id', $event->id)->first();
        abort_if($game === null, 404);

        $engine = $engines->live($game->type);
        if (! in_array($action, $engine->actions(), true)) {
            throw ActionRefused::unavailable();
        }

        // G15: actions that destroy or decide carry the version the host saw.
        $seen = in_array($action, $engine->versionedActions(), true) ? SeenVersion::from($request) : null;

        $run->handle($engine, $action, $event, $question, $user, $request->validated(), $seen);

        return response()->noContent();
    }
}
