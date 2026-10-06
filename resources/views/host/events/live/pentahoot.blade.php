{{-- F20, D-5, D-8, D-10: Live control for a Pentahoot game, in parts that Live control places in
     its columns: 'data' is the Alpine component around them, 'questions' goes under the steps,
     'control' in the middle, 'board' (the running count) in the monitor. --}}
@switch($part)
    @case('data')
pentahootHost({
        actionUrl: @js(url("/host/{$event->id}/questions/__QUESTION__/__ACTION__")),
        labels: @js(__('pentahoot')),
        questionIds: @js($questions->pluck('id')->values()),
    })
        @break
    @case('questions')
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
        @break
    @case('control')
        {{-- The question on screen: countdown, answers, and the actions its state allows. --}}
        <section class="flex flex-col gap-5 rounded-card bg-surface p-6" aria-live="polite">
            <p x-show="(!question || phase === 'done') && !allDone" class="text-muted">{{ __('pentahoot.no_question') }}</p>
            {{-- D-10: the first question starts from the middle; after Pick a question the list decides. --}}
            <div x-show="(!question || phase === 'done') && firstToOpen" class="flex">
                <x-button type="button" data-test="open-next" x-bind:disabled="busy" @click="act('start', firstToOpen.id)">
                    <span x-text="label('open_next', { N: firstToOpen?.number ?? '' })"></span>
                </x-button>
            </div>
            {{-- D-8: after the last question the only step left is finishing the game. --}}
            <div x-cloak x-show="allDone" class="flex flex-col items-start gap-3 rounded-card bg-success px-4 py-3 text-on-success">
                <p class="font-semibold">{{ __('pentahoot.all_done') }}</p>
                @include('host.events.live.finish', ['label' => __('games.finish')])
            </div>
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
                        {{-- D-10: Next question goes on in order; Pick a question closes this one and leaves the choice to the list. --}}
                        <x-button type="button" x-show="phase !== 'ready' && nextToOpen" x-bind:disabled="busy || !can('next')" @click="advance()">{{ __('pentahoot.next') }}</x-button>
                        <x-button type="button" variant="secondary" x-show="phase !== 'ready' && nextToOpen" x-bind:disabled="busy || !can('next')" @click="act('next')">{{ __('pentahoot.pick') }}</x-button>
                        <x-button type="button" x-show="phase !== 'ready' && !nextToOpen" x-bind:disabled="busy || !can('next')" @click="act('next')">{{ __('pentahoot.close_question') }}</x-button>
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
        {{-- D-8, E13: finishing early leaves unfinished questions out and is logged. --}}
        <section x-show="!finished" class="flex flex-wrap items-center justify-between gap-3 rounded-card bg-surface p-6">
            <span class="font-semibold">{{ $game->title }}</span>
            @include('host.events.live.finish', ['label' => __('games.finish')])
        </section>
        @break
    @case('board')
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
        @break
@endswitch
