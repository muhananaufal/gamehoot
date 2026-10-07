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
        {{-- D-10: the question on screen and one main action for the step at hand (mainAction).
             Another question opens from the list under the steps; rare actions are in the game
             card menu. --}}
        <section class="flex flex-col gap-5 rounded-card bg-surface p-6" aria-live="polite">
            {{-- Always above the button while no question is open, as in Tebak. --}}
            <p x-show="(!question || phase === 'done') && !allDone" class="text-muted">{{ __('pentahoot.no_question') }}</p>
            <p x-cloak x-show="allDone" class="text-muted">{{ __('pentahoot.all_done') }}</p>
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
                </div>
            </template>
            {{-- The one main action. Busy keeps a quick double click from running the next step. --}}
            <div x-show="mainAction && mainAction.action !== 'finish'" class="flex justify-end">
                <x-button type="button" data-test="main-action" x-bind:disabled="busy || (mainAction?.action === 'stop' && !can('stop'))" @click="act(mainAction.action, mainAction.id)">
                    <span x-text="mainAction ? label(mainAction.label, { N: mainAction.number ?? '' }) : ''"></span>
                </x-button>
            </div>
            {{-- D-10: after the last question Finish game is the one step left; it closes the question too. --}}
            <div x-cloak x-show="mainAction?.action === 'finish'" class="flex justify-end">
                @include('host.events.live.finish', ['label' => __('games.finish'), 'direct' => true])
            </div>
            <p x-show="error" role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger" x-text="error"></p>
        </section>
        @include('host.events.live.game-card', ['menu' => [['label' => __('pentahoot.reset'), 'click' => "document.getElementById('reset-question').showModal()", 'show' => "can('reset')"]], 'dialogs' => 'host.events.live.reset-dialog'])
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
