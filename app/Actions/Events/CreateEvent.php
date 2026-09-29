<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\ScreenTheme;
use App\Models\Event;
use App\Models\User;

final class CreateEvent
{
    public function handle(User $owner, string $name, string $slug, ScreenTheme $theme, bool $showOnDevices): Event
    {
        $event = new Event([
            'name' => trim($name),
            'slug' => $slug,
            'screen_theme' => $theme,
            'show_on_devices' => $showOnDevices,
        ]);
        $event->owner()->associate($owner);
        $event->save();

        return $event;
    }
}
