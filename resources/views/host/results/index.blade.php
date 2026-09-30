{{-- D-3, D-7, G10: results read from the frozen tables. --}}
<x-layouts.host :title="__('results.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('results.title'), null]]">
    <x-slot:actions>
        <x-button :href="route('host.events.results.export', $event)" variant="secondary">{{ __('results.export') }}</x-button>
    </x-slot:actions>

    <section class="flex flex-col gap-2 rounded-card bg-surface p-6">
        <p class="text-sm text-muted">{{ __('results.subtitle') }}</p>
        @if ($results->lastChange)
            <p class="text-sm font-semibold">{{ __('results.last_change', ['time' => $results->lastChange->timezone(config('app.display_timezone'))->format('j M Y, H:i')]) }}</p>
            <p class="text-sm text-muted">{{ __('results.reopened_note') }}</p>
        @endif
    </section>

    @forelse ($results->games as $game)
        <section class="flex flex-col gap-4 rounded-card bg-surface p-6">
            <h2 class="font-display text-xl font-semibold">{{ $game['title'] }}</h2>
            @foreach ($game['questions'] as $question)
                <article class="flex flex-col gap-2 border-t border-line-soft pt-4">
                    <h3 class="font-bold"><span class="text-muted">{{ __('results.question', ['number' => $question['number']]) }} ·</span> {{ $question['prompt'] }}</h3>
                    @if (! $question['played'])
                        <p class="text-sm text-muted">{{ __('results.not_played') }}</p>
                    @elseif ($game['type'] === \App\Enums\GameType::TebakKata)
                        {{-- Tebak: the answer and its winner (D-6), or no winner after a Surrender. --}}
                        <p class="flex flex-wrap items-baseline gap-x-3 text-sm">
                            <span class="font-display font-semibold tracking-wide">{{ $question['answer'] }}</span>
                            @if ($question['winner'] !== null)
                                <span class="font-semibold">{{ $question['winner'] }}</span>
                                <span class="text-muted">{{ trans_choice('results.points', (int) $question['points']) }}</span>
                            @else
                                <span class="text-muted">{{ __('results.no_winner') }}</span>
                            @endif
                        </p>
                    @elseif ($question['rows'] === [])
                        <p class="text-sm text-muted">{{ __('results.no_votes') }}</p>
                    @else
                        <table class="w-full max-w-xl text-sm">
                            <thead class="text-left text-muted">
                                <tr>
                                    <th class="w-16 py-1 font-semibold">{{ __('results.rank') }}</th>
                                    <th class="py-1 font-semibold">{{ __('results.name') }}</th>
                                    <th class="w-20 py-1 text-right font-semibold">{{ __('results.votes') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($question['rows'] as $row)
                                    <tr class="border-t border-line-soft">
                                        <td class="py-1.5 font-display font-semibold tabular-nums">{{ $row['rank'] }}</td>
                                        <td class="py-1.5">{{ $row['name'] }}</td>
                                        <td class="py-1.5 text-right tabular-nums">{{ $row['votes'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </article>
            @endforeach

            {{-- E10, E12, G10: the final board frozen when the game finished. --}}
            @if ($game['final'] !== null)
                <div class="flex flex-col gap-2 border-t border-line-soft pt-4">
                    <h3 class="font-bold">{{ __('results.final') }}</h3>
                    <table class="w-full max-w-xl text-sm">
                        <thead class="text-left text-muted">
                            <tr>
                                <th class="w-16 py-1 font-semibold">{{ __('results.rank') }}</th>
                                <th class="py-1 font-semibold">{{ __('results.name') }}</th>
                                <th class="w-20 py-1 text-right font-semibold">{{ __('results.points_header') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($game['final'] as $row)
                                <tr class="border-t border-line-soft">
                                    <td class="py-1.5 font-display font-semibold tabular-nums">{{ $row['rank'] }}</td>
                                    <td class="py-1.5">{{ $row['name'] }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ $row['points'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @empty
        <p class="rounded-card bg-surface p-6 text-muted">{{ __('results.empty') }}</p>
    @endforelse
</x-layouts.host>
