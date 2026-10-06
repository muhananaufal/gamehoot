{{-- D-10: the running game, what comes after it, and the two ways to end early: Close game ends
     this game (D-8), Close event ends it and closes the event (T6). --}}
<section x-show="!finished" class="flex flex-wrap items-center justify-between gap-3 rounded-card bg-surface p-6">
    <div class="flex min-w-0 flex-col">
        <span class="font-semibold">{{ $game->title }}</span>
        @if ($next ?? null)
            <span class="text-sm text-muted">{{ __('live.up_next', ['title' => $next->position . '. ' . $next->title]) }}</span>
        @endif
        @if ($help ?? null)
            <span class="text-sm text-muted">{{ $help }}</span>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-2.5">
        @include('host.events.partials.close-event', ['event' => $event, 'runningGame' => $game])
        @include('host.events.live.finish', ['label' => __('games.close_game')])
    </div>
</section>
