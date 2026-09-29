<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * D-3, D-7: lifecycle of an event.
 */
enum EventStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Finished = 'finished';
}
