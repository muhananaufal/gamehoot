<?php

declare(strict_types=1);

// K4: one message per fixed error code.
return [
    'STALE_ACTION' => 'The screen was out of date. It has been refreshed, try again.',
    'ACTION_UNAVAILABLE' => 'This action is not available for this question right now.',
    'GAME_RUNNING' => 'Another game is running. Finish it first.',
    'GAME_FINISHED' => 'This game has already been played.',
    'NO_QUESTIONS' => 'This game has no questions yet.',
    'GAME_PLAYED' => 'This game has been played, so it is kept for the results.',
    'EVENT_NOT_OPEN' => 'Open the event before starting a game.',
    'GAME_STARTED' => 'This game has started, so it can no longer be changed.',
    'QUESTION_LOCKED' => 'This question has been on screen, so it can no longer be edited.',
    'VOTE_CLOSED' => 'Voting for this question has closed.',
    'ALREADY_VOTED' => 'You have already voted for this question.',
    'NOT_CLAIMED' => 'Pick your name first.',

    // Framework errors on JSON requests.
    'NOT_FOUND' => 'This item no longer exists. Reload the page.',
    'FORBIDDEN' => 'You do not have access to this.',
    'UNAUTHENTICATED' => 'You are signed out. Sign in again.',
    'SESSION_EXPIRED' => 'This page was open too long. Reload it and try again.',
    'TOO_MANY_REQUESTS' => 'Too many tries at once. Wait a moment and try again.',
    'VALIDATION_FAILED' => 'Some fields need attention.',
    'SERVICE_UNAVAILABLE' => 'Pentahoot is being updated. Try again in a minute.',
    'SERVER_ERROR' => 'Something went wrong on our side. Try again.',
    'HTTP_ERROR' => 'The request could not be completed.',
];
