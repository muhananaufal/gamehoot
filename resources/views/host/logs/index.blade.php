@php
    $tz = config('app.display_timezone');
@endphp
{{-- E13: irreversible actions of the event, newest first, in WIB (T10). --}}
<x-layouts.host :title="__('logs.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('logs.title'), null]]">
    <x-realtime :event="$event" :audience="\App\Enums\Audience::Host" />
    {{-- U12: new entries appear while the page is open. --}}
    <section id="log-live" x-data="liveRegion" class="flex flex-col gap-4">
        @if ($entries === [])
            <x-empty-state :title="__('logs.empty')" class="py-8" />
        @else
            {{-- U4: search, filter by who and by date, sort and pages in the browser; the view state stays in localStorage. --}}
            <div x-data="tableView({ key: 'logs:{{ $event->id }}', sort: 'at', dir: 'desc' })" class="flex flex-col gap-4">
                <x-table.toolbar :search="__('logs.search')" :today="now()->timezone($tz)->format('Y-m-d')" :date-label="__('logs.date_filter')">
                    {{-- U23: Who sits beside Rows per page. --}}
                    <x-select :options="[
                            ['value' => 'all', 'label' => __('logs.everyone')],
                            ...array_map(fn (array $actor): array => ['value' => $actor['id'], 'label' => $actor['name']], $actors),
                        ]" :label="__('logs.who')" x-model="who" />
                </x-table.toolbar>

                <div class="rounded-card bg-surface max-lg:overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-body">
                        <thead class="z-10 border-b border-line-soft bg-surface text-xs font-bold tracking-[0.06em] text-muted uppercase lg:sticky lg:top-16">
                            <tr>
                                <x-table.th sort="at">{{ __('logs.when') }}</x-table.th>
                                <x-table.th sort="who">{{ __('logs.who') }}</x-table.th>
                                <x-table.th sort="what">{{ __('logs.what') }}</x-table.th>
                            </tr>
                        </thead>
                        <tbody class="[&_td]:px-4 [&_td]:py-2.5 [&_td]:align-top [&_td:first-child]:pl-6 [&>tr]:border-b [&>tr]:border-line-soft [&>tr:last-child]:border-0 [&_small]:block [&_small]:text-sm [&_small]:text-muted">
                            @foreach ($entries as $entry)
                                <tr data-row data-search="{{ mb_strtolower($entry['actor'] . ' ' . $entry['action'] . ' ' . $entry['subject']) }}" data-sort-at="{{ $entry['at']->getTimestamp() }}"
                                    data-sort-who="{{ $entry['actor'] }}" data-sort-what="{{ $entry['action'] }}" data-who="{{ $entry['actor_id'] }}" data-date="{{ $entry['at']->timezone($tz)->format('Y-m-d') }}">
                                    <td class="whitespace-nowrap tabular-nums">{{ $entry['at']->timezone($tz)->format('j M, H:i') }}</td>
                                    <td>{{ $entry['actor'] }}</td>
                                    <td>
                                        {{ $entry['action'] }}
                                        @if ($entry['subject'])
                                            <small>{{ $entry['subject'] }}</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p x-show="shown === 0" x-cloak class="px-6 py-8 text-center text-muted">{{ __('logs.no_match') }}</p>
                    <x-table-pager />
                </div>
            </div>
        @endif
    </section>
</x-layouts.host>
