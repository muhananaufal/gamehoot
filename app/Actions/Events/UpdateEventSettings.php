<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\LoggedAction;
use App\Enums\ScreenTheme;
use App\Models\Event;
use App\Models\User;
use App\Realtime\StatePublisher;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

final readonly class UpdateEventSettings
{
    public function __construct(private StatePublisher $publisher, private AuditLog $auditLog) {}

    public function handle(Event $event, string $name, string $slug, ScreenTheme $theme, bool $showOnDevices, User $actor): void
    {
        DB::transaction(function () use ($event, $name, $slug, $theme, $showOnDevices, $actor): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);

            // F10: printed QR codes and open phones still use the old link.
            if ($locked->slug !== $slug) {
                $this->auditLog->record(LoggedAction::LinkChanged, $actor, $locked, ['from' => $locked->slug, 'to' => $slug]);
            }

            $locked->fill([
                'name' => trim($name),
                'slug' => $slug,
                'screen_theme' => $theme,
                'show_on_devices' => $showOnDevices,
            ]);
            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);
        });
    }
}
