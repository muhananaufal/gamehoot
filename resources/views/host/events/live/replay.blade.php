{{-- D-11: Replay on screen, under the Public View preview. A past moment comes back with one
     click, without a dialog (U24 rhythm exception): nothing changes for good and Back to live
     undoes it. The card refreshes with the event's version (U12), so new moments and the
     question on screen show up without reloading the page. --}}
@php
    use App\Enums\ReplayKind;

    $label = fn ($moment): string => match ($moment->kind) {
        ReplayKind::Final => __('replay.final'),
        ReplayKind::Leaderboard => __('replay.leaderboard', ['number' => $moment->number]),
        ReplayKind::Result => 'Q' . $moment->number . ' · ' . $moment->title,
    };
    // The same numbers as the steps column, so games of the same pack can be told apart.
    $gameLabel = fn ($game): string => $game->position . '. ' . $game->title;
    $isCurrent = fn ($moment): bool => $replay['current'] !== null && $moment->is(ReplayKind::from($replay['current']['kind']), $replay['current']['game'], $replay['current']['question']);
    $currentLabel = null;

    foreach ($replay['groups'] as $group) {
        foreach ($group['moments'] as $moment) {
            if ($isCurrent($moment)) {
                $currentLabel = $gameLabel($group['game']) . ' · ' . $label($moment);
            }
        }
    }
@endphp
<div id="live-replay" x-data="liveRegion">
    @if ($replay['groups'] !== [])
        <section aria-labelledby="replay-heading" x-init="$store.replayPick.game = @js($replay['current']['game'] ?? null) ?? ($store.replayPick.game && @js(array_map(fn ($group) => $group['game']->id, $replay['groups'])).includes($store.replayPick.game) ? $store.replayPick.game : @js($replay['pick']))"
            class="flex flex-col gap-3 rounded-card bg-surface p-5">
            <div class="flex flex-col gap-1">
                <h2 id="replay-heading" class="text-sm font-bold text-muted">{{ __('replay.card_title') }}</h2>
                <p class="text-xs text-muted">{{ __('replay.card_help') }}</p>
            </div>

            @if ($currentLabel !== null)
                <div class="flex flex-col gap-2 rounded-control bg-subtle p-3">
                    <p class="text-sm font-semibold" data-test="replay-showing">{{ __('replay.showing', ['moment' => $currentLabel]) }}</p>
                    <form method="POST" action="{{ route('host.events.replay.destroy', $event) }}" class="flex justify-end">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit">{{ __('replay.back_to_live') }}</x-button>
                    </form>
                </div>
            @endif

            @if ($replay['busy'])
                <p class="text-sm text-muted">{{ __('replay.between_questions') }}</p>
            @endif

            <x-select :options="array_map(fn ($group) => ['value' => $group['game']->id, 'label' => $gameLabel($group['game'])], $replay['groups'])"
                :label="__('replay.game')" x-model="$store.replayPick.game" class="w-full" />

            @foreach ($replay['groups'] as $group)
                <ol x-show="$store.replayPick.game === @js($group['game']->id)" class="flex flex-col gap-0.5">
                    @foreach ($group['moments'] as $moment)
                        @php $current = $isCurrent($moment); @endphp
                        <li @class(['flex min-h-11 items-center gap-2 rounded-control px-2', 'bg-subtle font-bold' => $current]) @if ($current) aria-current="true" @endif>
                            <span class="flex min-w-0 grow flex-col">
                                <span class="truncate text-sm">{{ $label($moment) }}</span>
                                @if ($moment->kind === ReplayKind::Result)
                                    <span class="truncate text-xs text-muted">{{ $moment->names === [] ? __('replay.no_winner') : implode(', ', $moment->names) }}</span>
                                @endif
                            </span>
                            <form method="POST" action="{{ route('host.events.replay.store', $event) }}">
                                @csrf
                                <input type="hidden" name="kind" value="{{ $moment->kind->value }}">
                                <input type="hidden" name="game" value="{{ $moment->gameId }}">
                                @if ($moment->questionId !== null)
                                    <input type="hidden" name="question" value="{{ $moment->questionId }}">
                                @endif
                                <button type="submit" class="row-action" data-test="replay-show" @disabled($replay['busy'] || $current)
                                    aria-label="{{ __('replay.show', ['moment' => $gameLabel($group['game']) . ' · ' . $label($moment)]) }}" title="{{ __('replay.show', ['moment' => $label($moment)]) }}"><i data-icon="play"></i></button>
                            </form>
                        </li>
                    @endforeach
                </ol>
            @endforeach
        </section>
    @endif
</div>
