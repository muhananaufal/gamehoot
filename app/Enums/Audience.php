<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Event;

/**
 * F2, F18: the snapshot channels of an event, named by the event UUID. Whatever goes to the
 * public channel is open to anyone who knows the UUID.
 */
enum Audience: string
{
    case Public = 'public';
    case Host = 'host';

    public function channelName(Event $event): string
    {
        return "event.{$event->id}.{$this->value}";
    }

    /**
     * F25: the public channel of the counters that change often (answered, joined). Only the
     * Public View and Live control follow it; phones do not.
     */
    public static function counterChannelName(Event $event): string
    {
        return "event.{$event->id}.screen";
    }
}
