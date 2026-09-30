<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Event;

/**
 * F2, F18: two channels per event, named by the event UUID. Whatever goes to the public
 * channel is open to anyone who knows the UUID.
 */
enum Audience: string
{
    case Public = 'public';
    case Host = 'host';

    public function channelName(Event $event): string
    {
        return "event.{$event->id}.{$this->value}";
    }
}
