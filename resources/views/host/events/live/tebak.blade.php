{{-- F20, D-10: Live control for a Tebak game (Kata or Gambar), in parts that Live control places
     in its columns ('data', 'questions', 'control', 'board', as for Pentahoot). Show any queued
     question (D-5), skip once (E6, E7), pick a winner after a confirmation (D-6) or surrender
     (E13), show the leaderboard (E11) and the final results (E12). The question itself, with
     hints (Kata, E5) or Reveal (Gambar, E9), comes from live/tebak/{type}. --}}
@switch($part)
    @case('data')
tebakHost({
        type: @js($game->type->value),
        actionUrl: @js(url("/host/{$event->id}/questions/__QUESTION__/__ACTION__")),
        people: @js($people),
        labels: @js([...__('tebak'), 'network_error' => __('pentahoot.network_error')]),
        questionIds: @js($questions->pluck('id')->values()),
    })
        @break
    @case('questions')
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
        @break
    @case('control')
        <p x-show="error" role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger" x-text="error"></p>
        {{-- The question on screen, with the answer only hosts see. --}}
        <section class="flex flex-col gap-5 rounded-card bg-surface p-6" aria-live="polite">
            <p x-show="!question && !allResolved" class="text-muted">{{ __('tebak.no_question') }}</p>
            {{-- D-10: the first question starts from the middle; after that, Next question or the list. --}}
            <div x-show="!question && firstToOpen" class="flex justify-end">
                <x-button type="button" data-test="open-next" x-bind:disabled="busy" @click="act('show', firstToOpen.id)">
                    <span x-text="label('show_next', { N: firstToOpen?.number ?? '' })"></span>
                </x-button>
            </div>
            {{-- D-8, D-10: after the last question the only step left is Finish game (final results, E12). --}}
            <p x-cloak x-show="allResolved" class="text-muted">{{ __('tebak.all_done') }}</p>
            <div x-cloak x-show="allResolved" class="flex justify-end">
                @include('host.events.live.finish', ['label' => __('games.finish'), 'direct' => true])
            </div>
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
                {{-- D-10: × clears the search and the picked name. --}}
                <div class="relative flex items-center">
                    <input id="winner-search" placeholder="{{ __('examples.search_name') }}" x-ref="winnerSearch" type="text" enterkeyhint="search" x-model="query" autocomplete="off" spellcheck="false"
                        class="h-11 w-full rounded-control border border-line-strong bg-field pr-11 pl-3 text-ink">
                    <button type="button" data-test="clear-winner" x-show="query !== '' || chosen" x-cloak @click="query = ''; chosen = null; $refs.winnerSearch?.focus()"
                        class="absolute right-0 flex size-11 items-center justify-center rounded-control text-muted hover:text-ink"
                        aria-label="{{ __('tebak.clear_winner') }}" title="{{ __('tebak.clear_winner') }}"><x-icon name="close" class="size-4" /></button>
                </div>
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
                {{-- D-10: actions on the right, the main one last. --}}
                <div class="flex flex-wrap justify-end gap-2.5">
                    {{-- U24: Skip uses the one skip of the question, so it asks first. --}}
                    <x-button type="button" variant="secondary" data-test="skip" x-bind:disabled="!canSkip || busy" @click="document.getElementById('skip-question').showModal()" :title="__('tebak.skip_help')">{{ __('tebak.skip') }}</x-button>
                    {{-- D-10: off until a name is picked. --}}
                    <x-button type="button" data-test="pick-winner" x-bind:disabled="!chosen || busy" @click="document.getElementById('pick-winner').showModal()">{{ __('tebak.pick_winner') }}</x-button>
                </div>
                {{-- Outside the row: their empty wrappers would add gaps and push the buttons left. --}}
                    <x-confirm-dialog :title="__('tebak.skip_title')" :trigger="__('tebak.skip')" hide-trigger dialog-id="skip-question">
                        <p class="text-[15px] text-muted">{{ __('tebak.skip_help') }}</p>
                        <form method="dialog" class="flex justify-end gap-2.5 pt-1.5">
                            <x-button variant="secondary" type="submit">{{ __('ui.cancel') }}</x-button>
                            <x-button type="button" data-test="confirm-skip" x-bind:disabled="busy" @click="$el.closest('dialog').close(); act('skip')">{{ __('tebak.skip') }}</x-button>
                        </form>
                    </x-confirm-dialog>
                    <x-confirm-dialog :title="__('tebak.confirm_winner_title')" :trigger="__('tebak.pick_winner')" hide-trigger dialog-id="pick-winner">
                        <p class="text-[15px] text-muted" x-text="chosen ? @js(__('tebak.confirm_winner', ['name' => '__NAME__'])).replace('__NAME__', chosen.name) : ''"></p>
                        {{-- The confirm button closes the dialog itself: once act() sets busy, Alpine disables
                             it before the click's default action, so the dialog form would never submit. --}}
                        <form method="dialog" class="flex justify-end gap-2.5 pt-1.5">
                            <x-button variant="secondary" type="submit">{{ __('ui.cancel') }}</x-button>
                            <x-button type="button" data-test="confirm-winner" x-bind:disabled="!chosen || busy" @click="$el.closest('dialog').close(); act('winner', question.id, { person: chosen.id })">{{ __('tebak.pick_winner') }}</x-button>
                        </form>
                    </x-confirm-dialog>
                    {{-- D-10: Surrender is rare; it opens from the game card menu. --}}
                    <x-confirm-dialog :title="__('tebak.surrender_title')" :trigger="__('tebak.surrender')" hide-trigger dialog-id="give-up">
                        <p class="text-[15px] text-muted">{{ __('tebak.surrender_help') }}</p>
                        <form method="dialog" class="flex justify-end gap-2.5 pt-1.5">
                            <x-button variant="secondary" type="submit">{{ __('ui.cancel') }}</x-button>
                            <x-button type="submit" variant="destructive" @click="act('surrender')">{{ __('tebak.surrender') }}</x-button>
                        </form>
                    </x-confirm-dialog>
            </div>

            {{-- D-10: actions on the right, the main one last. --}}
            <div x-show="(question?.status === 'won' || question?.status === 'surrendered') && (nextToOpen || canLeaderboard)" class="flex flex-wrap justify-end gap-2.5 border-t border-line-soft pt-4">
                <x-button type="button" variant="secondary" data-test="show-leaderboard" x-show="canLeaderboard" x-bind:disabled="busy" @click="act('leaderboard')">{{ __('tebak.show_leaderboard') }}</x-button>
                <x-button type="button" data-test="next-question" x-show="nextToOpen" x-bind:disabled="busy" @click="act('show', nextToOpen.id)">{{ __('tebak.next_question') }}</x-button>
            </div>
        </section>
        {{-- E12, D-8: Close game shows the final results early; unplayed questions do not count. --}}
        @include('host.events.live.game-card', ['menu' => [
            ['label' => __('tebak.surrender'), 'click' => "document.getElementById('give-up').showModal()", 'show' => "question?.status === 'shown'"],
        ]])
        @break
    @case('board')
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
        @break
@endswitch
