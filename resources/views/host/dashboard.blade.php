@php
    use App\Enums\EventStatus;

    $statusTone = fn (EventStatus $s): string => match ($s) {
        EventStatus::Open => 'success',
        EventStatus::Draft => 'neutral',
        EventStatus::Finished => 'warning',
    };
    $tz = config('app.display_timezone');
@endphp
<x-layouts.host :title="__('host.dashboard.title')" :breadcrumbs="[[__('host.dashboard.title'), null]]">
    <x-slot:actions>
        <x-button :href="route('host.events.create')"><x-icon name="plus" />{{ __('events.new_event') }}</x-button>
    </x-slot:actions>

    @if ($events->isEmpty())
        <x-empty-state :title="__('host.dashboard.empty_title')" :body="__('host.dashboard.empty')">
            <x-button variant="secondary" :href="route('host.packs.index')">{{ __('host.nav.question_packs') }}</x-button>
        </x-empty-state>
    @else
        {{-- U4: filter, search and sort in the browser; the state stays in the URL. --}}
        <div x-data="tableView({ sort: 'changed', dir: 'desc' })" class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('host.dashboard.status') }}">
                    @foreach ([null, ...EventStatus::cases()] as $filter)
                        @php
                            $key = $filter?->value ?? 'all';
                            $label = $filter ? __('host.event_status.' . $filter->value) : __('host.dashboard.all');
                            $count = $filter ? $counts->get($filter->value, 0) : $total;
                        @endphp
                        <button type="button" @click="filter = @js($key)" :aria-pressed="(filter === @js($key)).toString()"
                            class="inline-flex h-11 items-center rounded-control px-3.5 text-sm font-semibold"
                            :class="filter === @js($key) ? 'bg-inverse text-on-inverse' : 'border border-line-strong bg-surface text-ink hover:bg-subtle'">{{ $label }} · {{ $count }}</button>
                    @endforeach
                </div>
                <label class="relative flex w-full items-center sm:w-72">
                    <x-icon name="search" class="pointer-events-none absolute left-3 text-muted" />
                    <input type="search" x-model.debounce.150ms="q" aria-label="{{ __('host.dashboard.search') }}" placeholder="{{ __('host.dashboard.search') }}" autocomplete="off"
                        class="h-11 w-full rounded-control border border-line-strong bg-field pr-3 pl-10 text-[15px] text-ink">
                </label>
            </div>

            <div class="rounded-card bg-surface max-lg:overflow-x-auto">
                <table class="w-full min-w-[640px] text-left">
                    <thead class="z-10 border-b border-line-soft bg-surface text-xs font-bold tracking-[0.06em] text-muted uppercase lg:sticky lg:top-16">
                        <tr>
                            <th scope="col" class="px-6 py-1" :aria-sort="ariaSort('name')">
                                <button type="button" class="inline-flex min-h-11 items-center gap-1.5 uppercase hover:text-ink" @click="sortBy('name')">{{ __('host.dashboard.event') }} <x-icon name="sort" class="size-3.5" /></button>
                            </th>
                            <th scope="col" class="px-4 py-3">{{ __('host.dashboard.status') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('host.dashboard.names') }}</th>
                            <th scope="col" class="px-4 py-3">{{ __('host.dashboard.games') }}</th>
                            <th scope="col" class="px-4 py-1" :aria-sort="ariaSort('changed')">
                                <button type="button" class="inline-flex min-h-11 items-center gap-1.5 uppercase hover:text-ink" @click="sortBy('changed')">{{ __('host.dashboard.changed') }} <x-icon name="sort" class="size-3.5" /></button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="[&>tr]:border-b [&>tr]:border-line-soft [&>tr:last-child]:border-0">
                        @foreach ($events as $event)
                            <tr data-row data-search="{{ mb_strtolower($event->name . ' ' . $event->slug . ' ' . $event->owner?->name) }}" data-filter="{{ $event->status->value }}"
                                data-sort-name="{{ $event->name }}" data-sort-changed="{{ $event->updated_at?->getTimestamp() }}">
                                <td class="px-6 py-3.5">
                                    <a href="{{ route('host.events.live', $event) }}" class="block text-base font-bold hover:text-link">{{ $event->name }}</a>
                                    {{-- U6: when it was made, and whose it is when the host only co-hosts it. --}}
                                    <span class="text-[13px] text-muted">/{{ $event->slug }} · {{ __('host.dashboard.created', ['date' => $event->created_at?->timezone($tz)->format('j M Y')]) }}@if ($event->owner_id !== $user->id) · {{ __('host.dashboard.owned_by', ['name' => $event->owner?->name]) }}@endif</span>
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
                                <td class="px-4 py-3.5 text-sm text-muted tabular-nums">{{ __('host.dashboard.changed_at', ['time' => $event->updated_at?->timezone($tz)->format('j M, H:i')]) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p x-show="shown === 0" x-cloak class="px-6 py-8 text-center text-muted">{{ __('host.dashboard.empty_filtered') }}</p>
            </div>
        </div>
    @endif
</x-layouts.host>
