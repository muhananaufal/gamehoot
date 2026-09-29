<?php

declare(strict_types=1);

namespace App\Http\Controllers\Join;

use App\Actions\People\ClaimName;
use App\Exceptions\ClaimRefused;
use App\Http\Requests\Join\ClaimRequest;
use App\Http\Views\PhoneStatus;
use App\Models\Event;
use App\People\ClaimCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * B-1, F12: the general join page lists the names nobody has claimed yet.
 */
final class JoinController
{
    public function index(Request $request, Event $event): Response|RedirectResponse
    {
        if (ClaimCookie::person($request, $event) !== null) {
            return redirect()->route('join.play', $event);
        }

        if ($status = PhoneStatus::forEvent($event)) {
            return $status;
        }

        return response()->view('join.index', [
            'event' => $event,
            'names' => $event->people()->whereNull('claimed_at')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(ClaimRequest $request, Event $event, ClaimName $claimName): Response|RedirectResponse
    {
        $person = $event->people()->findOrFail($request->string('person')->toString());

        try {
            $token = $claimName->handle($event, $person);
        } catch (ClaimRefused $refused) {
            return PhoneStatus::refused($event, $refused, $person->name);
        }

        return redirect()->route('join.play', $event)->withCookie(ClaimCookie::make($event, $token));
    }
}
