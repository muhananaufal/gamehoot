{{-- E13: irreversible actions of the event, newest first, in WIB (T10). --}}
<x-layouts.host :title="__('logs.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('logs.title'), null]]">
    <section class="flex flex-col gap-4 rounded-card bg-surface p-6">
        <p class="text-sm text-muted">{{ __('logs.subtitle') }}</p>
        @if ($entries === [])
            <p class="text-muted">{{ __('logs.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-muted">
                        <tr>
                            <th class="py-1 pr-4 font-semibold">{{ __('logs.when') }}</th>
                            <th class="py-1 pr-4 font-semibold">{{ __('logs.who') }}</th>
                            <th class="py-1 font-semibold">{{ __('logs.what') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr class="border-t border-line-soft align-top">
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
            </div>
        @endif
    </section>
</x-layouts.host>
