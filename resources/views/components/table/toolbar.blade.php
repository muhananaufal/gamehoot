@props(['search', 'today' => null, 'dateLabel' => null])
{{-- U4: the bar above every host table, inside a tableView. Left: rows per page, then the
     list's own filters (the slot). Right: Reset filters, extra filters ($right), the date range,
     and the search box in the far right corner. --}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3']) }}>
    <div class="flex flex-wrap items-center gap-2">
        <label class="inline-flex h-11 items-center gap-2 text-sm text-muted">
            <span>{{ __('ui.rows_per_page') }}</span>
            <select x-model.number="size" class="h-11 cursor-pointer rounded-control border border-line-strong bg-field px-3 text-[15px] font-semibold text-ink tabular-nums">
                @foreach ([10, 25, 50, 100] as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </label>
        {{ $slot }}
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <x-reset-filters />
        {{ $right ?? '' }}
        @if ($today)
            <x-date-range-picker :today="$today" :label="$dateLabel" />
        @endif
        <label class="relative flex w-full items-center sm:w-72">
            <x-icon name="search" class="pointer-events-none absolute left-3 text-muted" />
            <input type="search" x-model.debounce.150ms="q" aria-label="{{ $search }}" placeholder="{{ $search }}" autocomplete="off"
                class="h-11 w-full rounded-control border border-line-strong bg-field pr-3 pl-10 text-[15px] text-ink">
        </label>
    </div>
</div>
