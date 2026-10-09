{{-- D-9: the games of the event by status, in the style of the pack list (U14). Up next comes first
     and can be dragged into a new play order; a search shows once there are more than seven games
     (U22). One filter, the game type, shows which game is which. The open game is marked. --}}
@php
    $statusTone = ['up_next' => 'bg-nav-icon', 'running' => 'bg-success-dot', 'played' => 'bg-warning-dot'];
@endphp
<nav id="games-list" aria-label="{{ __('games.title') }}" x-data="{ q: '', type: 'all' }"
    class="flex flex-col gap-3 self-start rounded-card bg-surface p-4 lg:sticky lg:top-20 lg:max-h-[calc(100dvh-6rem)] lg:overflow-y-auto">
    @if ($count > 7)
        <label class="relative flex items-center">
            <x-icon name="search" class="pointer-events-none absolute left-3 text-muted" />
            <input type="search" x-model.debounce.150ms="q" aria-label="{{ __('games.search') }}" placeholder="{{ __('games.search') }}" autocomplete="off"
                class="h-11 w-full rounded-control border border-line-strong bg-field pr-3 pl-10 text-body text-ink">
        </label>
    @endif
    @if ($count > 0)
        <x-select :options="[
            ['value' => 'all', 'label' => __('packs.all_types')],
            ...array_map(fn (App\Enums\GameType $type): array => ['value' => $type->value, 'label' => __('games.types.' . $type->value)], $types),
        ]" :label="__('packs.filter_type')" x-model="type" />
    @endif
    @if ($count === 0)
        <p class="px-2 py-1 text-sm text-muted">{{ __('games.empty_title') }}</p>
    @endif
    @foreach ($groups as $status => $items)
        @continue($items->isEmpty())
        <section x-data="{ open: true }" x-show="type === 'all' || {{ \Illuminate\Support\Js::from($items->map(fn ($item) => $item->type->value)->unique()->values()) }}.includes(type)" class="flex flex-col gap-1">
            <button type="button" @click="open = ! open" :aria-expanded="open.toString()"
                class="flex min-h-9 items-center justify-between rounded-control px-2 text-[11px] font-bold tracking-[0.08em] text-muted uppercase hover:bg-subtle hover:text-ink">
                {{-- The count follows the type filter. --}}
                <span x-data="{ counts: {{ \Illuminate\Support\Js::from($items->countBy(fn ($item) => $item->type->value)) }} }"
                    x-text="@js(__('games.group.' . $status)) + ' · ' + (type === 'all' ? {{ $items->count() }} : (counts[type] ?? 0))">{{ __('games.group.' . $status) }} · {{ $items->count() }}</span>
                <x-icon name="chevron-right" class="size-4 transition-transform" ::class="open && 'rotate-90'" />
            </button>
            <div x-show="open" @if ($status === 'up_next') x-data="sortableList" data-reload="#games-list" data-save-failed="{{ __('games.order_failed') }}" @endif>
                @if ($status === 'up_next')
                    <form method="POST" action="{{ route('host.events.games.reorder', $event) }}" x-ref="orderForm" class="hidden">
                        @csrf
                    </form>
                @endif
                <ol class="flex flex-col gap-0.5" @if ($status === 'up_next') x-flip.moves @endif>
                    @foreach ($items as $item)
                        <li data-type="{{ $item->type->value }}" data-game="{{ $item->id }}" @if ($status === 'up_next') data-id="{{ $item->id }}" draggable="true"
                                @dragstart="start($event, {{ $loop->index }})" @dragenter.prevent="enter({{ $loop->index }})" @dragover.prevent
                                @drop.prevent="drop({{ $loop->index }})" @dragend="end()"
                                :class="{ 'opacity-50': from === {{ $loop->index }}, 'bg-subtle': over === {{ $loop->index }} && from !== {{ $loop->index }} }" @endif
                            x-show="(type === 'all' || type === '{{ $item->type->value }}') && (q === '' || {{ \Illuminate\Support\Js::from(mb_strtolower($item->title)) }}.includes(q.toLowerCase()))"
                            class="flex items-center gap-1 rounded-control">
                            @if ($status === 'up_next')
                                <span class="flex h-11 w-5 shrink-0 cursor-grab items-center justify-center text-muted active:cursor-grabbing" title="{{ __('games.drag') }}" aria-hidden="true"><x-icon name="grip" class="size-4" /></span>
                            @endif
                            <a href="{{ route('host.events.games.show', [$event, $item]) }}" @if ($game?->is($item)) aria-current="page" @endif
                                class="flex min-h-11 min-w-0 grow items-center gap-2 rounded-control px-2 text-body hover:bg-subtle aria-[current=page]:bg-subtle aria-[current=page]:font-bold">
                                <span class="size-2 shrink-0 rounded-full {{ $statusTone[$status] }}" aria-hidden="true"></span>
                                <span class="truncate">{{ $item->title }}</span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endforeach
</nav>
