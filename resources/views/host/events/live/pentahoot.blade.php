{{-- F20, D-5, D-8: Live control for a Pentahoot game. A finished game stays on screen (G7). --}}
<div x-data="pentahootHost({
        actionUrl: @js(url("/host/{$event->id}/questions/__QUESTION__/__ACTION__")),
        labels: @js(__('pentahoot')),
    })"
    class="flex flex-col gap-5">
    <section x-cloak x-show="finished" class="flex flex-col items-start gap-3 rounded-card bg-surface p-6">
        <p class="font-semibold">{{ $game->title }}</p>
        <p class="text-sm text-muted">{{ __('pentahoot.game_over_host') }}</p>
        <x-button :href="route('host.events.games.index', $event)" variant="secondary">{{ __('pentahoot.open_games') }}</x-button>
    </section>

    <div x-show="!finished" class="grid gap-5 xl:grid-cols-[minmax(0,320px)_minmax(0,1fr)_minmax(0,320px)]">

        {{-- D-5: any question that is not done can be opened. --}}
        <section class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-labelledby="questions-heading">
            <h2 id="questions-heading" class="font-display text-xl font-semibold">{{ __('pentahoot.questions') }} ({{ $questions->count() }})</h2>
            <x-timeline :steps="$questions->map(fn ($question) => [
                'label' => $question->pentahoot?->prompt ?? '',
                'hint' => __('pentahoot.status.' . $question->status->value),
                'hintExpr' => 'labels.status[statusOf(' . $question->position . ')]',
                'stateExpr' => 'timelineState(' . $question->position . ')',
                'click' => 'act(\'start\', ' . \Illuminate\Support\Js::from($question->id) . ')',
                'clickable' => 'canOpen(' . $question->position . ') && !busy',
            ])->all()" />
        </section>

        {{-- The question on screen: countdown, answers, and the actions its state allows. --}}
        <section class="flex flex-col gap-5 rounded-card bg-surface p-6" aria-live="polite">
            <p x-show="!question || phase === 'done'" class="text-muted">{{ __('pentahoot.no_question') }}</p>
            <template x-if="question && phase !== 'done'">
                <div class="flex flex-col gap-5">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-sm font-bold text-muted uppercase"
                            x-text="label('question_of', { N: question.number, TOTAL: game.count }) + ' · ' + labels.status[phase]"></span>
                        <span x-show="phase === 'live'" class="font-display text-3xl font-bold tabular-nums" x-text="seconds"></span>
                    </div>
                    <p class="font-display text-2xl leading-tight font-semibold" x-text="question.prompt"></p>
                    <p x-show="phase === 'live' || phase === 'closed'" class="font-display text-xl font-semibold tabular-nums"
                        x-text="label('answered_of', { N: answered, TOTAL: total })"></p>
                    {{-- E15: the question does not close by itself when everyone answered. --}}
                    <p x-show="allAnswered" class="rounded-card bg-success px-4 py-3 text-sm font-semibold text-on-success"
                        x-text="label('all_answered', { N: answered, TOTAL: total })"></p>
                    <x-rank-list x-show="phase === 'revealed'" rows="results" />
                    <p x-show="phase === 'revealed' && results.length === 0" class="text-muted">{{ __('pentahoot.no_votes') }}</p>
                    <div class="flex flex-wrap gap-2.5">
                        <x-button type="button" x-show="phase === 'ready'" x-bind:disabled="busy" @click="act('start')">{{ __('pentahoot.start') }}</x-button>
                        <x-button type="button" x-show="phase !== 'ready'" x-bind:disabled="busy || !can('stop')" @click="act('stop')">{{ __('pentahoot.stop') }}</x-button>
                        <x-button type="button" x-show="phase !== 'ready'" x-bind:disabled="busy || !can('reveal')" @click="act('reveal')">{{ __('pentahoot.reveal') }}</x-button>
                        <x-button type="button" x-show="phase !== 'ready'" x-bind:disabled="busy || !can('next')" @click="act('next')">{{ __('pentahoot.next') }}</x-button>
                    </div>
                </div>
            </template>
            <p x-show="error" role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger" x-text="error"></p>

            {{-- T1, E13: Reset is irreversible and logged. --}}
            <div x-show="can('reset')" class="flex flex-wrap items-center justify-between gap-3 border-t border-line-soft pt-4">
                <div class="flex flex-col">
                    <span class="text-sm font-bold">{{ __('pentahoot.danger') }}</span>
                    <span class="text-sm text-muted">{{ __('pentahoot.reset_help') }}</span>
                </div>
                <x-confirm-dialog :title="__('pentahoot.reset_title')" :trigger="__('pentahoot.reset')" trigger-variant="destructive">
                    <p class="text-[15px] text-muted">{{ __('pentahoot.reset_help') }}</p>
                    <form method="dialog" class="flex justify-end gap-2.5 pt-1.5">
                        <x-button variant="secondary" type="submit">{{ __('ui.cancel') }}</x-button>
                        <x-button type="submit" variant="destructive" @click="act('reset')">{{ __('pentahoot.reset') }}</x-button>
                    </form>
                </x-confirm-dialog>
            </div>
        </section>

        {{-- F2, F14: the running count per name, for hosts only. --}}
        <section class="flex flex-col gap-3 rounded-card bg-surface p-6" aria-labelledby="tally-heading">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="tally-heading" class="font-display text-xl font-semibold">{{ __('pentahoot.tally') }}</h2>
                <span class="text-xs text-muted">{{ __('pentahoot.tally_note') }}</span>
            </div>
            <p x-show="tally.length === 0" class="text-sm text-muted">{{ __('pentahoot.tally_empty') }}</p>
            <ol class="flex flex-col gap-1.5">
                <template x-for="row in tally" :key="row.name">
                    <li class="flex justify-between gap-3 border-t border-line-soft pt-1.5">
                        <span class="truncate" x-text="row.name"></span>
                        <b class="tabular-nums" x-text="row.votes"></b>
                    </li>
                </template>
            </ol>
        </section>
    </div>

    {{-- D-8, E13: finishing early leaves unfinished questions out and is logged. --}}
    <section x-show="!finished" class="flex flex-wrap items-center justify-between gap-3 rounded-card bg-surface p-6">
        <span class="font-semibold">{{ $game->title }}</span>
        <x-confirm-dialog :title="__('games.finish') . '?'" :trigger="__('games.finish')">
            <p class="text-[15px] text-muted">{{ __('games.finish_confirm', ['title' => $game->title]) }}</p>
            <form method="POST" action="{{ route('host.events.games.finish', [$event, $game]) }}" class="flex justify-end gap-2.5 pt-1.5">
                @csrf
                <x-button variant="secondary" formmethod="dialog" type="submit">{{ __('ui.cancel') }}</x-button>
                <x-button type="submit">{{ __('games.finish') }}</x-button>
            </form>
        </x-confirm-dialog>
    </section>
</div>
