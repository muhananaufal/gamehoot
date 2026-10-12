@props([
    // Today in WIB as YYYY-MM-DD (T10), so presets count from the host's day, not the browser's.
    'today',
    'label',
])
{{-- U15: a themed range calendar inside a tableView; it edits the list's `from` and `to` on Apply
     (resources/js/date-range.js). Keyboard: arrows, Page Up/Down, Home/End, Enter, Escape. --}}
<div x-data="dateRangePicker({ today: '{{ $today }}' })" class="flex">
    <button type="button" @click="openPicker()" aria-label="{{ $label }}" title="{{ $label }}" aria-haspopup="dialog"
        class="inline-flex h-11 items-center gap-2 rounded-control border px-3.5 text-body-s font-semibold"
        :class="from || to ? 'border-transparent bg-inverse text-on-inverse' : 'border-field-line bg-surface text-ink hover:bg-subtle'">
        <x-icon name="calendar" class="size-4" />
        <span x-text="label || @js(__('ui.date'))"></span>
    </button>

    <dialog x-ref="picker" aria-labelledby="date-range-title"
        class="m-auto w-[min(660px,calc(100vw-2rem))] rounded-panel bg-surface p-0 text-ink shadow-2xl">
        <div class="flex flex-col gap-4 p-5.5">
            <div class="flex items-center justify-between gap-3">
                <h2 id="date-range-title" class="font-display text-title-s font-bold">{{ $label }}</h2>
                <div class="flex items-center gap-1">
                    <button type="button" @click="shift(-1)" aria-label="{{ __('ui.previous_month') }}" title="{{ __('ui.previous_month') }}"
                        class="flex size-11 items-center justify-center rounded-control hover:bg-subtle"><x-icon name="chevron-left" /></button>
                    <button type="button" @click="shift(1)" aria-label="{{ __('ui.next_month') }}" title="{{ __('ui.next_month') }}"
                        class="flex size-11 items-center justify-center rounded-control hover:bg-subtle"><x-icon name="chevron-right" /></button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('ui.quick_ranges') }}">
                @foreach (['today', 'last7', 'last30', 'month'] as $preset)
                    <button type="button" @click="preset(@js($preset))"
                        class="inline-flex h-11 items-center rounded-control border border-field-line px-3 text-body-s font-semibold hover:bg-subtle">{{ __('ui.range_' . $preset) }}</button>
                @endforeach
            </div>

            <div class="grid gap-6 sm:grid-cols-2" @keydown="onKey($event)">
                <template x-for="month in grid" :key="month.key">
                    <div class="flex flex-col gap-2">
                        <p class="text-center font-semibold" x-text="month.name"></p>
                        <table role="grid" class="w-full table-fixed border-collapse text-center text-body-s" :aria-label="month.name">
                            <thead>
                                <tr>
                                    @foreach (__('ui.weekdays') as $weekday)
                                        <th scope="col" class="pb-1 text-caption font-bold text-muted">{{ $weekday }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="week in month.weeks" :key="week[0].date">
                                    <tr>
                                        <template x-for="day in week" :key="day.date">
                                            <td role="gridcell" class="p-0">
                                                <button type="button" x-show="day.inMonth" :data-day="day.inMonth ? day.date : null" @click="pick(day.date)"
                                                    :tabindex="day.date === focusDay ? 0 : -1" :aria-label="dayLabel(day.date)"
                                                    class="flex h-11 w-full items-center justify-center tabular-nums transition-colors"
                                                    :class="cellClass(day.date)"
                                                    x-text="day.day"></button>
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2.5 border-t border-line-soft pt-4">
                <button type="button" @click="clear()" class="inline-flex h-11 items-center px-1 text-body-s font-semibold text-link hover:underline">{{ __('ui.clear') }}</button>
                <div class="flex gap-2.5">
                    <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                    <x-button type="button" @click="apply()">{{ __('ui.apply') }}</x-button>
                </div>
            </div>
        </div>
    </dialog>
</div>
