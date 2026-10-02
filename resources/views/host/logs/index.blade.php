{{-- E13: irreversible actions of the event, newest first, in WIB (T10). --}}
<x-layouts.host :title="__('logs.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('logs.title'), null]]">
    <x-realtime :event="$event" :audience="\App\Enums\Audience::Host" />
    {{-- U12: new entries appear while the page is open. --}}
    <section id="log-live" x-data="liveRegion" class="flex flex-col gap-4 rounded-card bg-surface p-6">
        <p class="text-sm text-muted">{{ __('logs.subtitle') }}</p>
        @if ($entries === [])
            <x-empty-state :title="__('logs.empty')" class="py-8" />
        @else
            {{-- U4: search and sort in the browser; the state stays in the URL. --}}
            <div x-data="tableView({ key: 'logs:{{ $event->id }}', sort: 'at', dir: 'desc' })" class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                <label class="relative flex w-full items-center sm:w-72">
                    <x-icon name="search" class="pointer-events-none absolute left-3 text-muted" />
                    <input type="search" x-model.debounce.150ms="q" aria-label="{{ __('logs.search') }}" placeholder="{{ __('logs.search') }}" autocomplete="off"
                        class="h-11 w-full rounded-control border border-line-strong bg-field pr-3 pl-10 text-[15px] text-ink">
                </label>
                <x-reset-filters />
                </div>
                <div class="max-lg:overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-muted">
                            <tr>
                                <th scope="col" class="pr-4 font-semibold" :aria-sort="ariaSort('at')">
                                    <button type="button" class="inline-flex min-h-11 items-center gap-1.5 hover:text-ink" @click="sortBy('at')">{{ __('logs.when') }} <x-icon name="sort" class="size-3.5" /></button>
                                </th>
                                <th scope="col" class="py-1 pr-4 font-semibold">{{ __('logs.who') }}</th>
                                <th scope="col" class="py-1 font-semibold">{{ __('logs.what') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr data-row data-search="{{ mb_strtolower($entry['actor'] . ' ' . $entry['action'] . ' ' . $entry['subject']) }}" data-sort-at="{{ $entry['at']->getTimestamp() }}" class="border-t border-line-soft align-top">
                                    <td class="py-2 pr-4 whitespace-nowrap tabular-nums">{{ $entry['at']->timezone(config('app.display_timezone'))->format('j M, H:i') }}</td>
                                    <td class="py-2 pr-4">{{ $entry['actor'] }}</td>
                                    <td class="py-2">
                                        {{ $entry['action'] }}
                                        @if ($entry['subject'])
                                            <span class="block text-muted">{{ $entry['subject'] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p x-show="shown === 0" x-cloak class="py-6 text-center text-muted">{{ __('logs.no_match') }}</p>
                    <x-table-pager class="px-0" />
                </div>
            </div>
        @endif
    </section>
</x-layouts.host>
