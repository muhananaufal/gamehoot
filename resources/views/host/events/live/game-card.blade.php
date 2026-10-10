{{-- D-10: the running game and what comes after it. The ways to end early and the game's own rare
     actions ($menu: label, Alpine click and condition) sit in one menu; Close
     game ends this game (D-8), Close event ends it and closes the event (T6). --}}
<section x-show="!finished" class="rounded-card bg-surface p-6">
    <div class="flex items-center justify-between gap-3">
    <div class="flex min-w-0 flex-col">
        <span class="font-semibold">{{ $game->title }}</span>
        @if ($next ?? null)
            <span class="text-body-s text-muted">{{ __('live.up_next', ['title' => $next->position . '. ' . $next->title]) }}</span>
        @endif
    </div>
    <div x-data="{ menu: false }" class="relative shrink-0" @keydown.escape="menu = false" @click.outside="menu = false">
        <button type="button" class="row-action" aria-haspopup="menu" :aria-expanded="menu.toString()" aria-label="{{ __('live.more_actions') }}" title="{{ __('live.more_actions') }}"
            @click="menu = ! menu"><i data-icon="more-vertical"></i></button>
        <div x-cloak x-show="menu" role="menu" class="absolute right-0 z-20 mt-1 flex min-w-52 flex-col rounded-control border border-line-soft bg-surface p-1 text-left text-body-s font-semibold shadow-xl">
            @foreach ($menu ?? [] as $item)
                <button type="button" role="menuitem" x-show="{{ $item['show'] }}" class="min-h-11 rounded-control px-3 text-left hover:bg-subtle"
                    @click="menu = false; {{ $item['click'] }}">{{ $item['label'] }}</button>
            @endforeach
            <button type="button" role="menuitem" class="min-h-11 rounded-control px-3 text-left hover:bg-subtle"
                @click="menu = false; document.getElementById('close-game').showModal()">{{ __('games.close_game') }}</button>
            <button type="button" role="menuitem" class="min-h-11 rounded-control px-3 text-left text-on-danger hover:bg-danger"
                @click="menu = false; document.getElementById('close-event').showModal()">{{ __('events.close') }}</button>
        </div>
    </div>
    </div>
    @include('host.events.live.finish', ['label' => __('games.close_game'), 'dialogId' => 'close-game'])
    @include('host.events.partials.close-event', ['event' => $event, 'runningGame' => $game, 'dialogId' => 'close-event'])
    {{-- The game's own dialogs, opened from the menu. --}}
    @if ($dialogs ?? null)
        @include($dialogs)
    @endif
</section>
