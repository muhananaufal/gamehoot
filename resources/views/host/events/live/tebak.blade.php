{{-- F20: Live control for a Tebak game (Kata or Gambar). Show any queued question (D-5), skip once
     (E6, E7), pick a winner after a confirmation (D-6) or surrender (E13), show the leaderboard
     (E11) and the final results (E12). The question itself, with hints (Kata, E5) or Reveal
     (Gambar, E9), comes from live/tebak/{type}. --}}
<div x-data="tebakHost({
        type: @js($game->type->value),
        actionUrl: @js(url("/host/{$event->id}/questions/__QUESTION__/__ACTION__")),
        people: @js($people),
        labels: @js([...__('tebak'), 'network_error' => __('pentahoot.network_error')]),
    })"
    class="flex flex-col gap-5">
    <p x-show="error" role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger" x-text="error"></p>

    <section x-cloak x-show="finished" class="flex flex-col items-start gap-3 rounded-card bg-surface p-6">
        <p class="font-semibold">{{ $game->title }}</p>
        <p class="text-sm text-muted">{{ __('pentahoot.game_over_host') }}</p>
        <x-button :href="route('host.events.games.index', $event)" variant="secondary">{{ __('pentahoot.open_games') }}</x-button>
    </section>

    <div x-show="!finished" class="grid gap-5 xl:grid-cols-[minmax(0,320px)_minmax(0,1fr)_minmax(0,320px)]">
        {{-- D-5, E6: the queue in pack order; skipped questions keep their place with a dashed mark. --}}
        <section class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-labelledby="tebak-questions-heading">
            <h2 id="tebak-questions-heading" class="font-display text-xl font-semibold">{{ __('tebak.questions') }} ({{ $questions->count() }})</h2>
            <x-timeline :steps="$questions->values()->map(fn ($question, $index) => [
                'label' => $question->kata?->prompt ?? $question->gambar?->title ?? '',
                'hint' => __('tebak.status.q'),
                'hintExpr' => 'statusOf(' . $index . ')',
                'stateExpr' => 'timelineState(' . $index . ')',
                'click' => 'act(\'show\', ' . \Illuminate\Support\Js::from($question->id) . ')',
                'clickable' => 'canShow(' . $index . ') && !busy',
            ])->all()" />
        </section>

        {{-- The question on screen, with the answer only hosts see. --}}
        <section class="flex flex-col gap-5 rounded-card bg-surface p-6" aria-live="polite">
            <p x-show="!question" class="text-muted">{{ __('tebak.no_question') }}</p>
            <template x-if="question">
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-bold text-muted uppercase" x-text="label('question_of', { N: question.number, TOTAL: game.count })"></span>
                        <span class="text-sm font-semibold" x-text="count('points', question.points)"></span>
                    </div>
                    @include('host.events.live.tebak.' . $game->type->value)
                    <p x-show="question.status === 'won'" class="rounded-card bg-rank-1 px-4 py-3 font-display text-lg font-bold text-on-rank-1">
                        <span>{{ __('tebak.winner') }}:</span> <span x-text="question.winner"></span>
                    </p>
                    <p x-show="question.status === 'surrendered'" class="font-semibold text-muted">{{ __('tebak.no_winner') }}</p>
                </div>
            </template>

            {{-- D-6: the winner is searched in the name list and confirmed before it is final. --}}
            <div x-show="question?.status === 'shown'" class="flex flex-col gap-3 border-t border-line-soft pt-4">
                <label for="winner-search" class="text-sm font-bold">{{ __('tebak.find_winner') }}</label>
                <input id="winner-search" type="search" x-model="query" autocomplete="off" spellcheck="false"
                    class="h-11 rounded-control border border-line-strong bg-field px-3 text-ink">
                <ul class="flex flex-col gap-1.5">
                    <template x-for="person in matches" :key="person.id">
                        <li>
                            <button type="button" @click="pick(person)"
                                class="flex min-h-11 w-full items-center rounded-control border px-3 text-left font-semibold"
                                :class="chosen?.id === person.id ? 'border-accent bg-subtle' : 'border-line bg-surface'"
                                :aria-pressed="(chosen?.id === person.id).toString()" x-text="person.name"></button>
                        </li>
                    </template>
                </ul>
                <div class="flex flex-wrap gap-2.5">
                    <x-confirm-dialog :title="__('tebak.confirm_winner_title')" :trigger="__('tebak.pick_winner')" trigger-variant="primary">
                        <p class="text-[15px] text-muted" x-text="chosen ? @js(__('tebak.confirm_winner', ['name' => '__NAME__'])).replace('__NAME__', chosen.name) : ''"></p>
                        {{-- The confirm button closes the dialog itself: once act() sets busy, Alpine disables
                             it before the click's default action, so the dialog form would never submit. --}}
                        <form method="dialog" class="flex justify-end gap-2.5 pt-1.5">
                            <x-button variant="secondary" type="submit">{{ __('ui.cancel') }}</x-button>
                            <x-button type="button" data-test="confirm-winner" x-bind:disabled="!chosen || busy" @click="$el.closest('dialog').close(); act('winner', question.id, { person: chosen.id })">{{ __('tebak.pick_winner') }}</x-button>
                        </form>
                    </x-confirm-dialog>
                    <x-button type="button" variant="secondary" x-bind:disabled="!canSkip || busy" @click="act('skip')" :title="__('tebak.skip_help')">{{ __('tebak.skip') }}</x-button>
                    <x-confirm-dialog :title="__('tebak.surrender_title')" :trigger="__('tebak.surrender')" trigger-variant="destructive">
                        <p class="text-[15px] text-muted">{{ __('tebak.surrender_help') }}</p>
                        <form method="dialog" class="flex justify-end gap-2.5 pt-1.5">
                            <x-button variant="secondary" type="submit">{{ __('ui.cancel') }}</x-button>
                            <x-button type="submit" variant="destructive" @click="act('surrender')">{{ __('tebak.surrender') }}</x-button>
                        </form>
                    </x-confirm-dialog>
                </div>
            </div>

            <div x-show="question?.status === 'won' || question?.status === 'surrendered'" class="flex flex-wrap gap-2.5 border-t border-line-soft pt-4">
                <x-button type="button" data-test="show-leaderboard" x-show="canLeaderboard" x-bind:disabled="busy" @click="act('leaderboard')">{{ __('tebak.show_leaderboard') }}</x-button>
            </div>
        </section>

        {{-- E10, E11: the board as it stands, for hosts. --}}
        <section class="flex flex-col gap-3 rounded-card bg-surface p-6" aria-labelledby="live-board-heading">
            <h2 id="live-board-heading" class="font-display text-xl font-semibold">{{ __('tebak.live_board') }}</h2>
            <p x-show="liveBoard.length === 0" class="text-sm text-muted">{{ __('tebak.live_board_empty') }}</p>
            <ol class="flex flex-col gap-1.5">
                <template x-for="row in liveBoard" :key="row.name">
                    <li class="flex justify-between gap-3 border-t border-line-soft pt-1.5">
                        <span class="truncate"><b class="tabular-nums" x-text="row.rank + '.'"></b> <span x-text="row.name"></span></span>
                        <b class="tabular-nums" x-text="row.points"></b>
                    </li>
                </template>
            </ol>
        </section>
    </div>

    {{-- E12, D-8: show the final results; unplayed questions do not count and ending early is logged. --}}
    <section x-show="!finished" class="flex flex-wrap items-center justify-between gap-3 rounded-card bg-surface p-6">
        <div class="flex flex-col">
            <span class="font-semibold">{{ $game->title }}</span>
            <span class="text-sm text-muted">{{ __('tebak.final_help') }}</span>
        </div>
        <x-confirm-dialog :title="__('tebak.show_final') . '?'" :trigger="__('tebak.show_final')">
            <p class="text-[15px] text-muted">{{ __('games.finish_confirm', ['title' => $game->title]) }}</p>
            <form method="POST" action="{{ route('host.events.games.finish', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
                @csrf
                <x-button variant="secondary" formmethod="dialog" type="submit">{{ __('ui.cancel') }}</x-button>
                <x-button type="submit">{{ __('tebak.show_final') }}</x-button>
            </form>
        </x-confirm-dialog>
    </section>
</div>
