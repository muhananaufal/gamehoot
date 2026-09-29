<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\ScreenTheme;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

final class UpdateEventSettings
{
    public function handle(Event $event, string $name, string $slug, ScreenTheme $theme, bool $showOnDevices): void
    {
        DB::transaction(function () use ($event, $name, $slug, $theme, $showOnDevices): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            $locked->fill([
                'name' => trim($name),
                'slug' => $slug,
                'screen_theme' => $theme,
                'show_on_devices' => $showOnDevices,
            ]);
            $locked->bumpStateVersion();
            $locked->save();
        });
    }
}
