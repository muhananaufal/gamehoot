{{-- D-9: a game row: title, its markers, type and questions, and when it was added or started (U6). --}}
<div class="flex min-w-0 grow flex-col gap-0.5">
    <div class="flex min-w-0 flex-wrap items-center gap-1.5">
        <h3 class="truncate font-bold">{{ $game->title }}</h3>
        @if ($game->packChanged())
            {{-- D-10: purple, since yellow means finished. --}}
            <x-chip tone="accent">{{ __('games.pack_changed') }}</x-chip>
        @endif
        @if ($game->edited_at)
            <x-chip>{{ __('games.edited') }}</x-chip>
        @endif
        @if ($game->packDeleted())
            <x-chip>{{ __('games.pack_deleted') }}</x-chip>
        @endif
    </div>
    <p class="text-sm text-muted">{{ __('games.types.' . $game->type->value) }} · <a href="{{ route('host.events.games.questions.index', [$event, $game]) }}" class="text-link underline-offset-4 hover:underline">{{ trans_choice('games.questions', $game->questions_count) }}</a></p>
    {{-- U6: when it was added, or when it started (from the activity log), in WIB (T10). --}}
    <p class="text-[13px] text-muted">
        @if (isset($startedAt[$game->id]))
            {{ __('games.started_at', ['time' => $startedAt[$game->id]->timezone(config('app.display_timezone'))->format('j M, H:i')]) }}
        @else
            {{ __('games.added_at', ['date' => $game->created_at?->timezone(config('app.display_timezone'))->format('j M')]) }}
        @endif
    </p>
</div>
