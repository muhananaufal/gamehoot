@php
    use App\Enums\EventStatus;

    $statusTone = fn (EventStatus $s): string => match ($s) {
        EventStatus::Open => 'success',
        EventStatus::Draft => 'neutral',
        EventStatus::Finished => 'warning',
    };
@endphp
<x-layouts.host :title="__('host.dashboard.title')" :breadcrumbs="[[__('host.dashboard.title'), null]]">
    <x-slot:actions>
        <x-button :href="route('host.events.create')">{{ __('events.new_event') }}</x-button>
    </x-slot:actions>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="flex flex-wrap gap-2" aria-label="{{ __('host.dashboard.status') }}">
            @foreach ([null, ...EventStatus::cases()] as $filter)
                @php
                    $active = $status === $filter;
                    $label = $filter ? __('host.event_status.' . $filter->value) : __('host.dashboard.all');
                    $count = $filter ? $counts->get($filter->value, 0) : $total;
                @endphp
                <a href="{{ route('host.dashboard', $filter ? ['status' => $filter->value] : []) }}"
                    @if ($active) aria-current="page" @endif
                    @class([
                        'inline-flex h-11 items-center rounded-control px-3.5 text-sm font-semibold',
                        'bg-inverse text-on-inverse' => $active,
                        'border border-line-strong bg-surface text-ink hover:bg-subtle' => ! $active,
                    ])>{{ $label }} · {{ $count }}</a>
            @endforeach
        </nav>
        <p class="text-sm text-muted">{{ __('host.dashboard.subtitle') }}</p>
    </div>

    <div class="overflow-x-auto rounded-card bg-surface">
        @if ($events->isEmpty())
            <p class="px-6 py-10 text-center text-muted">
                {{ $status ? __('host.dashboard.empty_filtered') : __('host.dashboard.empty') }}
            </p>
        @else
            <table class="w-full min-w-[640px] text-left">
                <thead class="border-b border-line-soft text-xs font-bold tracking-[0.06em] text-muted uppercase">
                    <tr>
                        <th scope="col" class="px-6 py-3">{{ __('host.dashboard.event') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('host.dashboard.status') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('host.dashboard.names') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('host.dashboard.games') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('host.dashboard.your_role') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr class="border-b border-line-soft last:border-0">
                            <td class="px-6 py-4">
                                <a href="{{ route('host.events.edit', $event) }}" class="block text-base font-bold hover:text-accent">{{ $event->name }}</a>
                                <span class="text-[13px] text-muted">/{{ $event->slug }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <x-chip :tone="$statusTone($event->status)" :dot="$event->status === EventStatus::Open">
                                    {{ __('host.event_status.' . $event->status->value) }}
                                </x-chip>
                            </td>
                            <td class="px-4 py-4 text-[15px] tabular-nums">{{ trans_choice('host.dashboard.names_count', $event->people_count) }}</td>
                            <td @class(['px-4 py-4 text-[15px]', 'font-semibold text-on-danger' => $event->games_count === 0])>
                                {{ trans_choice('host.dashboard.games_count', $event->games_count) }}
                            </td>
                            <td class="px-4 py-4 text-[15px]">
                                {{ $event->owner_id === $user->id ? __('host.dashboard.owner') : __('host.dashboard.cohost') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</x-layouts.host>
