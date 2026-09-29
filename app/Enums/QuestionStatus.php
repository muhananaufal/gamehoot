<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * G5: Pentahoot uses ready, live, revealed, done (closed is derived from ends_at). Tebak games use queued, shown, won, surrendered.
 */
enum QuestionStatus: string
{
    case Ready = 'ready';
    case Live = 'live';
    case Revealed = 'revealed';
    case Done = 'done';
    case Queued = 'queued';
    case Shown = 'shown';
    case Won = 'won';
    case Surrendered = 'surrendered';
}
