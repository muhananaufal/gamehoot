<?php

declare(strict_types=1);

namespace App\Http\Controllers\Join;

use App\Actions\People\ClaimName;
use App\Exceptions\ClaimRefused;
use App\Http\Views\PhoneStatus;
use App\Models\Event;
use App\People\ClaimCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * B-1: opening a personal link claims its owner's name straight away. A phone that already
 * holds this name just goes back to its game, even while claims are locked (B-7).
 */
final class PersonalLinkController
{
    public function __invoke(Request $request, Event $event, string $token, ClaimName $claimName): Response|RedirectResponse
    {
        $person = $event->people()->where('join_token', $token)->first();

        if ($person === null) {
            return PhoneStatus::linkInvalid($event);
        }

        if (ClaimCookie::person($request, $event)?->is($person)) {
            return redirect()->route('join.play', $event);
        }

        if ($status = PhoneStatus::forEvent($event)) {
            return $status;
        }

        try {
            $claimToken = $claimName->handle($event, $person);
        } catch (ClaimRefused $refused) {
            return PhoneStatus::refused($event, $refused, $person->name);
        }

        return redirect()->route('join.play', $event)->withCookie(ClaimCookie::make($event, $claimToken));
    }
}
