@props([
    // Packs grouped by game type value, each with questions_count.
    'packs',
    // The game types to show, in order.
    'types',
    // The open pack (Question packs), marked aria-current.
    'current' => null,
    // D-9: in the Add games dialog a click picks the pack (packPicker, resources/js/pack-picker.js)
    // and shows the number of its turn in the pick.
    'pick' => false,
])
@php use App\Enums\GameType; @endphp
{{-- U14, U23: the pack list, grouped by game type, with its own scroll, kept in view. Items carry
     data only; packList (resources/js/pack-list.js) searches, filters, orders and folds them.
     On Question packs the items open a pack: navigation.js gives them their Unpoly settings
     (another pack swaps the panel, the title with its actions and the breadcrumb) and Unpoly
     marks the open pack, also on its question pages (up-alias). On Games they add the pack. --}}
<nav id="pack-list" x-data="packList" aria-label="{{ __('packs.title') }}"
    {{-- In the Add games dialog the list fills the dialog and scrolls inside it; on a page it keeps beside the content. --}}
    class="{{ $pick ? 'w-full max-h-[50dvh] overflow-y-auto' : 'self-start rounded-card bg-surface p-4 lg:sticky lg:top-20 lg:max-h-[calc(100dvh-6rem)] lg:overflow-y-auto' }} flex flex-col gap-3 [&_[data-items]]:flex [&_[data-items]]:flex-col [&_[data-items]]:gap-0.5 [&_[data-pack]]:flex [&_[data-pack]]:w-full [&_[data-pack]]:min-h-11 [&_[data-pack]]:text-left [&_[data-pack]]:items-center [&_[data-pack]]:justify-between [&_[data-pack]]:gap-2 [&_[data-pack]]:rounded-control [&_[data-pack]]:px-3 [&_[data-pack]]:text-body [&_[data-pack]:hover]:bg-subtle [&_[data-pack][aria-current]]:bg-subtle [&_[data-pack][aria-current]]:font-bold [&_[data-pack]>span:first-child]:truncate [&_[data-pack]>span:last-child]:text-body-s [&_[data-pack]>span:last-child]:text-muted [&_[data-pack]>span:last-child]:tabular-nums">
    <label class="relative flex items-center">
        <x-icon name="search" class="pointer-events-none absolute left-3 text-muted" />
        <input type="search" x-model.debounce.150ms="q" aria-label="{{ __('packs.search') }}" placeholder="{{ __('packs.search') }}" autocomplete="off"
            class="h-11 w-full rounded-control border border-field-line bg-field pr-3 pl-10 text-body text-ink">
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
    @foreach ($types as $groupType)
        @continue(! $packs->has($groupType->value))
        @php $group = $packs->get($groupType->value); @endphp
        <section data-group="{{ $groupType->value }}" class="flex flex-col gap-1">
            <button type="button" @click="toggle('{{ $groupType->value }}')" :aria-expanded="isOpen('{{ $groupType->value }}').toString()"
                class="flex min-h-11 items-center justify-between rounded-control px-2 text-caption font-bold tracking-[0.08em] text-muted uppercase hover:bg-subtle hover:text-ink">
                <span>{{ __('games.types.' . $groupType->value) }} · {{ $group->count() }}</span>
                <x-icon name="chevron-right" class="size-4 transition-transform" ::class="isOpen('{{ $groupType->value }}') && 'rotate-90'" />
            </button>
            <div data-items>
@foreach ($group as $item)
@if ($pick)
<button type="button" data-pack data-type="{{ $groupType->value }}" data-changed="{{ $item->updated_at?->getTimestamp() }}" data-title="{{ $item->title }}" @click="pick({{ \Illuminate\Support\Js::from($item->id) }}, {{ \Illuminate\Support\Js::from($item->title) }})" :aria-pressed="(orderOf({{ \Illuminate\Support\Js::from($item->id) }}) > 0).toString()" :class="orderOf({{ \Illuminate\Support\Js::from($item->id) }}) > 0 && 'bg-subtle font-bold'"><span class="flex min-w-0 items-center gap-2"><span class="flex size-6 shrink-0 items-center justify-center rounded-full border-2 text-caption font-bold" :class="orderOf({{ \Illuminate\Support\Js::from($item->id) }}) > 0 ? 'border-accent bg-accent text-on-accent' : 'border-field-line'" x-text="orderOf({{ \Illuminate\Support\Js::from($item->id) }}) || ''" aria-hidden="true"></span><span class="truncate">{{ $item->title }}</span></span><span>{{ $item->questions_count }}</span></button>
@else
<a href="{{ route('host.packs.show', $item, false) }}" data-pack data-type="{{ $groupType->value }}" data-changed="{{ $item->updated_at?->getTimestamp() }}"@if ($current?->is($item)) aria-current="page"@endif><span>{{ $item->title }}</span><span id="pack-count-{{ $item->id }}">{{ $item->questions_count }}</span></a>
@endif
@endforeach
            </div>
        </section>
    @endforeach
</nav>
