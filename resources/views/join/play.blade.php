<x-layouts.phone :title="$event->name" :event="$event" :person="$person">
    <x-realtime :event="$event" :audience="\App\Enums\Audience::Public" />
    <x-connection-status class="mb-4" />

    <div x-data="pentahootPhone({
            peopleUrl: @js(route('join.people', $event)),
            voteUrl: @js(url("/{$event->slug}/questions/__QUESTION__/vote")),
            labels: @js(__('pentahoot')),
        })"
        x-show="$store.realtime.screen() === 'live'" class="flex grow flex-col">

        {{-- No game yet: the waiting room. --}}
        <div x-show="view === 'waiting'" class="flex grow flex-col items-center justify-center gap-5 text-center">
            <x-logo :size="96" :wordmark="false" outline="stroke-canvas" />
            <h1 class="font-display text-[32px] leading-tight font-bold">{{ __('join.welcome', ['name' => $person->name]) }}</h1>
            <p class="max-w-xs text-lg text-muted">{{ __('join.waiting') }}</p>
        </div>

        {{-- A game runs, no question is open. --}}
        <div x-cloak x-show="view === 'next'" class="flex grow flex-col items-center justify-center gap-4 text-center">
            <x-logo :size="80" :wordmark="false" outline="stroke-canvas" />
            <h1 class="font-display text-[28px] leading-tight font-bold" x-text="$store.realtime.snapshot?.game?.title"></h1>
            <p class="max-w-xs text-lg text-muted">{{ __('pentahoot.get_ready') }}</p>
        </div>

        <div x-cloak x-show="question && view !== 'next' && view !== 'waiting'" class="flex items-center justify-between gap-3 pb-3 text-sm font-semibold text-muted">
            <span x-text="question ? label('question_of', { N: question.number, TOTAL: $store.realtime.snapshot.game.count }) : ''"></span>
            <span x-show="phase === 'live'" class="font-display text-lg text-ink tabular-nums" x-text="seconds"></span>
        </div>

        {{-- F9, E3: search the name list and vote. --}}
        <div x-cloak x-show="view === 'vote'" class="flex grow flex-col gap-4">
            <p class="font-display text-[24px] leading-tight font-bold" x-text="question?.prompt"></p>
            <label for="vote-search" class="text-sm font-bold">{{ __('pentahoot.search_label') }}</label>
            <input id="vote-search" type="search" x-model="query" autocomplete="off" autocapitalize="words" spellcheck="false"
                placeholder="{{ __('pentahoot.search_placeholder') }}"
                class="h-12 rounded-control border border-line-strong bg-field px-4 text-lg text-ink">
            <p x-show="people === null" class="text-sm text-muted">{{ __('pentahoot.loading_names') }}</p>
            <p x-show="people !== null && query.trim() !== '' && matches.length === 0" class="text-sm text-muted">{{ __('pentahoot.no_match') }}</p>
            <ul class="flex flex-col gap-2">
                <template x-for="person in matches" :key="person.id">
                    <li>
                        <button type="button" @click="pick(person)"
                            class="flex min-h-12 w-full items-center justify-between rounded-control border px-4 text-left font-semibold"
                            :class="chosen?.id === person.id ? 'border-accent bg-subtle' : 'border-line bg-surface'"
                            :aria-pressed="(chosen?.id === person.id).toString()">
                            <span x-text="person.name"></span>
                        </button>
                    </li>
                </template>
            </ul>
            <p x-show="error" role="alert" class="text-sm font-semibold text-on-danger" x-text="error"></p>
            <x-button type="button" class="mt-auto h-12 w-full" x-bind:disabled="!chosen || sending" @click="send()">
                <span x-text="sending ? labels.sending : labels.send"></span>
            </x-button>
        </div>

        {{-- E4: the answer that was sent. --}}
        <div x-cloak x-show="view === 'sent'" class="flex grow flex-col items-center justify-center gap-3 text-center" role="status">
            <p class="font-display text-[20px] leading-tight font-semibold text-muted" x-text="question?.prompt"></p>
            <h1 class="font-display text-[30px] font-bold">{{ __('pentahoot.sent') }}</h1>
            <p class="text-sm text-muted">{{ __('pentahoot.your_pick') }}</p>
            <p class="font-display text-[26px] font-bold" x-text="myVote?.target"></p>
            <p class="max-w-xs text-sm text-muted">{{ __('pentahoot.locked') }}</p>
        </div>

        {{-- E3: the countdown ended before this phone voted. --}}
        <div x-cloak x-show="view === 'time_up'" class="flex grow flex-col items-center justify-center gap-3 text-center" role="status">
            <h1 class="font-display text-[30px] font-bold">{{ __('pentahoot.time_up') }}</h1>
            <p class="max-w-xs text-muted">{{ __('pentahoot.time_up_body') }}</p>
            <p class="max-w-xs text-sm text-muted">{{ __('pentahoot.waiting_results') }}</p>
        </div>

        {{-- E4, E4b, E17: the same results as the Public View, as text. --}}
        <div x-cloak x-show="view === 'results'" class="flex grow flex-col gap-4">
            <h1 class="font-display text-[22px] font-bold" x-text="label('results_of', { N: question?.number })"></h1>
            <p class="text-muted" x-text="question?.prompt"></p>
            <p x-show="results.length === 0" class="rounded-card bg-surface px-4 py-6 text-center font-semibold">{{ __('pentahoot.no_votes') }}</p>
            {{-- E17: the same podium as the Public View, with ranks 4 and 5 below it. --}}
            <x-podium x-show="results.length > 0" stand="stand" class="pt-2" />
            <p x-show="results.length > 0" class="text-sm text-muted">{{ __('pentahoot.ties_note') }}</p>
            <p x-show="myVote" class="text-sm font-semibold"
                x-text="myVote ? @js(__('pentahoot.your_pick_was', ['name' => '__NAME__'])).replace('__NAME__', myVote.target) : ''"></p>
        </div>

        {{-- G7, E12: the Pentahoot game is over; the next game can start any time. --}}
        <div x-cloak x-show="view === 'finished'" class="flex grow flex-col items-center justify-center gap-4 text-center" role="status">
            <x-logo :size="80" :wordmark="false" outline="stroke-canvas" />
            <h1 class="font-display text-[28px] leading-tight font-bold" x-text="$store.realtime.snapshot?.game?.title"></h1>
            <p class="max-w-xs text-lg text-muted">{{ __('pentahoot.game_over') }}</p>
        </div>
    </div>

    {{-- Tebak Kata: phones mirror the Public View only when the host turned it on (E14), with no
         buttons; players answer out loud. The final podium always shows, as text (E17). --}}
    <div x-data="tebakScreen({ type: 'tebak_kata', labels: @js(__('tebak')) })" x-cloak x-show="$store.realtime.screen() === 'live' && game" class="flex grow flex-col">
        <div x-show="view === 'final'" class="flex grow flex-col gap-4">
            <h1 class="font-display text-[22px] font-bold"><span x-text="game?.title"></span> · {{ __('tebak.final') }}</h1>
            <x-podium stand="stand" class="pt-2" />
        </div>

        <div x-show="view !== 'final' && !mirrored" class="flex grow flex-col items-center justify-center gap-4 text-center" role="status">
            <x-logo :size="80" :wordmark="false" outline="stroke-canvas" />
            <h1 class="font-display text-[28px] leading-tight font-bold" x-text="game?.title"></h1>
            <p class="max-w-xs text-lg text-muted">{{ __('tebak.watch_screen') }}</p>
        </div>

        <div x-show="view !== 'final' && mirrored" class="flex grow flex-col gap-5">
            <div class="flex items-center justify-between gap-3 text-sm font-semibold text-muted">
                <span x-text="question ? label('question_of', { N: question.number, TOTAL: game.count }) : game?.title"></span>
                <span>{{ __('tebak.answer_out_loud') }}</span>
            </div>
            <p x-show="view === 'next'" class="grow content-center text-center text-lg text-muted">{{ __('tebak.next_soon') }}</p>
            <template x-if="question && view !== 'next' && view !== 'leaderboard'">
                <div class="flex flex-col gap-5">
                    <p class="font-display text-[24px] leading-tight font-bold" x-text="question.prompt"></p>
                    <x-kata-boxes words="question.boxes" class="text-[26px]" />
                    <p x-show="view === 'won'" class="rounded-card bg-rank-1 px-4 py-3 text-center font-display text-xl font-bold text-on-rank-1">
                        <span>{{ __('tebak.winner') }}:</span> <span x-text="question.winner"></span>
                    </p>
                    <p x-show="view === 'surrendered'" class="text-center font-semibold text-muted">{{ __('tebak.no_winner') }}</p>
                </div>
            </template>
            <div x-show="view === 'leaderboard'" class="flex flex-col gap-3">
                <h1 class="font-display text-[22px] font-bold">{{ __('tebak.leaderboard') }}</h1>
                <x-rank-list rows="board.map((row) => ({ ...row, votes: row.points }))" note="moveLabel(row)" />
            </div>
        </div>
    </div>

    <x-live-status :event="$event" />
</x-layouts.phone>
