@php
    use App\Enums\EventStatus;

    $statusTone = fn (EventStatus $s): string => match ($s) {
        EventStatus::Open => 'success',
        EventStatus::Draft => 'neutral',
        EventStatus::Finished => 'warning',
    };
    // U4: the Status column sorts in the order an event goes through.
    $statusOrder = fn (EventStatus $s): int => match ($s) {
        EventStatus::Draft => 1,
        EventStatus::Open => 2,
        EventStatus::Finished => 3,
    };
    $tz = config('app.display_timezone');
@endphp
<x-layouts.host :title="__('host.dashboard.title')" :breadcrumbs="[[__('host.dashboard.title'), null]]">
    <x-slot:actions>
        <x-button :href="route('host.events.create')" up-layer="new modal" up-history="false" up-accept-location="/host/*/people" up-size="medium"><x-icon name="plus" />{{ __('events.new_event') }}</x-button>
    </x-slot:actions>

    @if ($events->isEmpty())
        <x-empty-state :title="__('host.dashboard.empty_title')" :body="__('host.dashboard.empty')">
            <x-button variant="secondary" :href="route('host.packs.index')">{{ __('host.nav.question_packs') }}</x-button>
        </x-empty-state>
    @else
        {{-- U4: filter, search, sort and pages in the browser; the view state stays in localStorage. --}}
        <div x-data="tableView({ key: 'events', sort: 'created', dir: 'desc' })" class="flex flex-col gap-4">
            <x-table.toolbar :search="__('host.dashboard.search')" :today="now()->timezone($tz)->format('Y-m-d')" :date-label="__('host.dashboard.date_filter')">
                {{-- U4, U22: the status filter is a dropdown with a count per status. --}}
                <x-select :options="[
                    ['value' => 'all', 'label' => __('host.dashboard.all_statuses'), 'count' => $total],
                    ...array_map(fn (EventStatus $status): array => ['value' => $status->value, 'label' => __('host.event_status.' . $status->value), 'count' => $counts->get($status->value, 0)], EventStatus::cases()),
                ]" :label="__('host.dashboard.status')" x-model="filter" />
            </x-table.toolbar>

            <div class="rounded-card bg-surface max-lg:overflow-x-auto">
                <table class="w-full min-w-[640px] text-left">
                    <thead class="z-10 border-b border-line-soft bg-surface text-xs font-bold tracking-[0.06em] text-muted uppercase lg:sticky lg:top-16">
                        <tr>
                            <x-table.th sort="name">{{ __('host.dashboard.event') }}</x-table.th>
                            <x-table.th sort="status">{{ __('host.dashboard.status') }}</x-table.th>
                            <x-table.th sort="names">{{ __('host.dashboard.names') }}</x-table.th>
                            <x-table.th sort="games">{{ __('host.dashboard.games') }}</x-table.th>
                            <x-table.th sort="created">{{ __('host.dashboard.created_column') }}</x-table.th>
                        </tr>
                    </thead>
                    <tbody class="[&>tr]:border-b [&>tr]:border-line-soft [&>tr:last-child]:border-0">
                        @foreach ($events as $event)
                            <tr data-row data-search="{{ mb_strtolower($event->name . ' ' . $event->slug . ' ' . $event->owner?->name) }}" data-filter="{{ $event->status->value }}"
                                data-sort-name="{{ $event->name }}" data-sort-status="{{ $statusOrder($event->status) }}" data-sort-names="{{ $event->people_count }}" data-sort-games="{{ $event->games_count }}"
                                data-sort-created="{{ $event->created_at?->getTimestamp() }}" data-date="{{ $event->created_at?->timezone($tz)->format('Y-m-d') }}">
                                <td class="px-6 py-3.5">
                                    {{-- U21: the host's role is a pill next to the name; the owner of a co-hosted event stays below. --}}
                                    <span class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('host.events.live', $event) }}" class="text-base font-bold hover:text-link">{{ $event->name }}</a>
                                        <x-chip :tone="$event->owner_id === $user->id ? 'accent' : 'neutral'">{{ $event->owner_id === $user->id ? __('host.dashboard.owner') : __('host.dashboard.cohost') }}</x-chip>
                                    </span>
                                    {{-- U6: when it last changed, and whose it is when the host only co-hosts it. --}}
                                    <span class="text-[13px] text-muted">/{{ $event->slug }} · {{ __('host.dashboard.changed_at', ['time' => $event->updated_at?->timezone($tz)->format('j M, H:i')]) }}@if ($event->owner_id !== $user->id) · {{ __('host.dashboard.owned_by', ['name' => $event->owner?->name]) }}@endif</span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <x-chip :tone="$statusTone($event->status)" :dot="$event->status === EventStatus::Open">
                                        {{ __('host.event_status.' . $event->status->value) }}
                                    </x-chip>
                                </td>
                                <td class="px-4 py-3.5 text-[15px] tabular-nums">{{ trans_choice('host.dashboard.names_count', $event->people_count) }}</td>
                                <td @class(['px-4 py-3.5 text-[15px]', 'font-semibold text-on-danger' => $event->games_count === 0])>
                                    {{ trans_choice('host.dashboard.games_count', $event->games_count) }}
                                </td>
                                <td class="px-4 py-3.5 text-sm text-muted tabular-nums">{{ $event->created_at?->timezone($tz)->format('j M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p x-show="shown === 0" x-cloak class="px-6 py-8 text-center text-muted">{{ __('host.dashboard.empty_filtered') }}</p>
                <x-table-pager />
            </div>
        </div>
    @endif
</x-layouts.host>
