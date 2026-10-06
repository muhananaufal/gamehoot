@props([
    // Packs grouped by game type value, each with questions_count.
    'packs',
    // The game types to show, in order.
    'types',
    // The open pack (Question packs), marked aria-current.
    'current' => null,
    // U23: on the Games page, where a click on a pack adds it as a game (POST pack_id).
    'addTo' => null,
])
@php use App\Enums\GameType; @endphp
{{-- U14, U23: the pack list, grouped by game type, with its own scroll, kept in view. Items carry
     data only; packList (resources/js/pack-list.js) searches, filters, orders and folds them.
     On Question packs the items open a pack: navigation.js gives them their Unpoly settings
     (another pack swaps the panel, the title with its actions and the breadcrumb) and Unpoly
     marks the open pack, also on its question pages (up-alias). On Games they add the pack. --}}
<nav id="pack-list" x-data="packList" aria-label="{{ __('packs.title') }}"
    class="flex flex-col gap-3 self-start rounded-card bg-surface p-4 lg:sticky lg:top-20 lg:max-h-[calc(100dvh-6rem)] lg:overflow-y-auto [&_[data-items]]:flex [&_[data-items]]:flex-col [&_[data-items]]:gap-0.5 [&_[data-pack]]:flex [&_[data-pack]]:w-full [&_[data-pack]]:min-h-11 [&_[data-pack]]:text-left [&_[data-pack]]:items-center [&_[data-pack]]:justify-between [&_[data-pack]]:gap-2 [&_[data-pack]]:rounded-control [&_[data-pack]]:px-3 [&_[data-pack]]:text-[15px] [&_[data-pack]:hover]:bg-subtle [&_[data-pack][aria-current]]:bg-subtle [&_[data-pack][aria-current]]:font-bold [&_[data-pack]>span:first-child]:truncate [&_[data-pack]>span:last-child]:text-sm [&_[data-pack]>span:last-child]:text-muted [&_[data-pack]>span:last-child]:tabular-nums">
    <label class="relative flex items-center">
        <x-icon name="search" class="pointer-events-none absolute left-3 text-muted" />
        <input type="search" x-model.debounce.150ms="q" aria-label="{{ __('packs.search') }}" placeholder="{{ __('packs.search') }}" autocomplete="off"
            class="h-11 w-full rounded-control border border-line-strong bg-field pr-3 pl-10 text-[15px] text-ink">
    </label>
    <div class="grid grid-cols-2 gap-2">
        <x-select :options="[
            ['value' => 'all', 'label' => __('packs.all_types')],
            ...array_map(fn (GameType $type): array => ['value' => $type->value, 'label' => __('games.types.' . $type->value)], $types),
        ]" :label="__('packs.filter_type')" x-model="type" class="min-w-0" />
        <x-select :options="[
            ['value' => 'name', 'label' => __('packs.sort_name')],
            ['value' => 'changed', 'label' => __('packs.sort_changed')],
        ]" :label="__('packs.sort')" x-model="sort" class="min-w-0" />
    </div>
    @if ($addTo)
        {{-- U23: one form for the whole list; the pack clicked is the one added. --}}
        <form method="POST" action="{{ $addTo }}" class="contents">
        @csrf
    @endif
    @foreach ($types as $groupType)
        @continue(! $packs->has($groupType->value))
        @php $group = $packs->get($groupType->value); @endphp
        <section data-group="{{ $groupType->value }}" class="flex flex-col gap-1">
            <button type="button" @click="toggle('{{ $groupType->value }}')" :aria-expanded="isOpen('{{ $groupType->value }}').toString()"
                class="flex min-h-9 items-center justify-between rounded-control px-2 text-[11px] font-bold tracking-[0.08em] text-muted uppercase hover:bg-subtle hover:text-ink">
                <span>{{ __('games.types.' . $groupType->value) }} · {{ $group->count() }}</span>
                <x-icon name="chevron-right" class="size-4 transition-transform" ::class="isOpen('{{ $groupType->value }}') && 'rotate-90'" />
            </button>
            <div data-items>
@foreach ($group as $item)
@if ($addTo)
<button type="submit" name="pack_id" value="{{ $item->id }}" data-pack data-type="{{ $groupType->value }}" data-changed="{{ $item->updated_at?->getTimestamp() }}" aria-label="{{ __('games.add_pack', ['title' => $item->title]) }}"><span>{{ $item->title }}</span><span>{{ $item->questions_count }}</span></button>
@else
<a href="{{ route('host.packs.show', $item, false) }}" data-pack data-type="{{ $groupType->value }}" data-changed="{{ $item->updated_at?->getTimestamp() }}"@if ($current?->is($item)) aria-current="page"@endif><span>{{ $item->title }}</span><span id="pack-count-{{ $item->id }}">{{ $item->questions_count }}</span></a>
@endif
@endforeach
            </div>
        </section>
    @endforeach
    @if ($addTo)
        </form>
    @endif
</nav>
