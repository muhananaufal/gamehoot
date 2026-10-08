<x-layouts.phone :title="$event->name" :event="$event" :person="$person">
    <x-realtime :event="$event" :audience="\App\Enums\Audience::Public" :me="$me" />
    <x-connection-status class="mb-4" />
    <x-celebration />

    <div x-data="pentahootPhone({
            peopleUrl: @js(route('join.people', $event)),
            voteUrl: @js(url("/{$event->slug}/questions/__QUESTION__/vote")),
            joinUrl: @js(route('join.index', $event)),
            labels: @js(__('pentahoot')),
        })"
        x-show="$store.realtime.screen() === 'live'" class="flex grow flex-col">

        <div x-cloak x-show="isPentahoot && question && view !== 'next' && view !== 'waiting'" class="flex items-center justify-between gap-3 pb-3 text-sm font-semibold text-muted">
            <span x-text="question ? label('question_of', { N: question.number, TOTAL: $store.realtime.snapshot.game.count }) : ''"></span>
            <span x-show="phase === 'live'" class="font-display text-lg text-ink tabular-nums" x-text="seconds"></span>
        </div>

        {{-- U30: the scenes of the phone share one grid cell and move less and faster than the projector. --}}
        <div class="scene-stack phone-scenes grow">
        {{-- No game yet: the waiting room. --}}
        {{-- U8: joining succeeded, so the welcome pops in and the mascot floats while waiting. --}}
        <x-scene as="div" :cloak="false" show="view === 'waiting'" class="pop-in flex flex-col items-center justify-center gap-5 text-center">
            {{-- U29: Penta waves hello, then rests between short bursts. --}}
            <x-mascot :size="96" mood="wave" />
            <h1 class="font-display text-[32px] leading-tight font-bold" x-text="@js(__('join.welcome', ['name' => '__NAME__'])).replace('__NAME__', $store.realtime.me?.name ?? @js($person->name))">{{ __('join.welcome', ['name' => $person->name]) }}</h1>
            <p class="max-w-xs text-lg text-muted">{{ __('join.waiting') }}</p>
        </x-scene>

        {{-- A game runs, no question is open. --}}
        <x-scene as="div" show="view === 'next'" class="flex flex-col items-center justify-center gap-4 text-center">
            <x-mascot :size="80" mood="surprised" />
            <h1 class="font-display text-[28px] leading-tight font-bold" x-text="$store.realtime.snapshot?.game?.title"></h1>
            <p class="max-w-xs text-lg text-muted">{{ __('pentahoot.get_ready') }}</p>
        </x-scene>

        {{-- F9, E3: search the name list and vote. --}}
        <x-scene as="div" show="view === 'vote'" class="flex flex-col gap-4">
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
                            :aria-pressed="(chosen?.id === person.id).toString()"
                            x-nudge.pulse="chosen?.id === person.id ? person.id : null">
                            <span x-text="person.name"></span>
                        </button>
                    </li>
                </template>
            </ul>
            <p x-show="error" role="alert" class="text-sm font-semibold text-on-danger" x-text="error"></p>
            <x-button type="button" class="mt-auto h-12 w-full" x-bind:disabled="!chosen || sending" @click="send()">
                <span x-text="sending ? labels.sending : labels.send"></span>
            </x-button>
        </x-scene>

        {{-- E4: the answer that was sent. --}}
        <x-scene as="div" show="view === 'sent'" class="flex flex-col items-center justify-center gap-3 text-center" role="status">
            <p class="font-display text-[20px] leading-tight font-semibold text-muted" x-text="question?.prompt"></p>
            <x-mascot :size="72" mood="happy" />
            <h1 class="stamp-in font-display text-[30px] font-bold">{{ __('pentahoot.sent') }}</h1>
            <p class="text-sm text-muted">{{ __('pentahoot.your_pick') }}</p>
            <p class="font-display text-[26px] font-bold" x-text="myVote?.target"></p>
            <p class="max-w-xs text-sm text-muted">{{ __('pentahoot.locked') }}</p>
        </x-scene>

        {{-- E3: the countdown ended before this phone voted. --}}
        <x-scene as="div" show="view === 'time_up'" class="flex flex-col items-center justify-center gap-3 text-center" role="status">
            <x-mascot :size="72" mood="sad" />
            <h1 class="font-display text-[30px] font-bold">{{ __('pentahoot.time_up') }}</h1>
            <p class="max-w-xs text-muted">{{ __('pentahoot.time_up_body') }}</p>
            <p class="max-w-xs text-sm text-muted">{{ __('pentahoot.waiting_results') }}</p>
        </x-scene>

        {{-- E4, E4b, E17: the same results as the Public View, as text. --}}
        <x-scene as="div" show="view === 'results'" class="flex flex-col gap-4">
            <h1 class="font-display text-[22px] font-bold" x-text="label('results_of', { N: question?.number })"></h1>
            <p class="text-muted" x-text="question?.prompt"></p>
            <p x-show="results.length === 0" class="rounded-card bg-surface px-4 py-6 text-center font-semibold">{{ __('pentahoot.no_votes') }}</p>
            {{-- E17: the same podium as the Public View, with ranks 4 and 5 below it. --}}
            <x-podium x-show="results.length > 0" stand="stand" :score="__('pentahoot.votes_count')" self="$store.realtime.me?.name" class="pt-2" />
            <p x-show="results.length > 0" class="text-sm text-muted">{{ __('pentahoot.ties_note') }}</p>
            <p x-show="myVote" class="text-sm font-semibold"
                x-text="myVote ? @js(__('pentahoot.your_pick_was', ['name' => '__NAME__'])).replace('__NAME__', myVote.target) : ''"></p>
        </x-scene>

        {{-- G7, E12: the Pentahoot game is over; the next game can start any time. --}}
        <x-scene as="div" show="view === 'finished'" class="flex flex-col items-center justify-center gap-4 text-center" role="status">
            <x-mascot :size="80" mood="wave" />
            <h1 class="font-display text-[28px] leading-tight font-bold" x-text="$store.realtime.snapshot?.game?.title"></h1>
            <p class="max-w-xs text-lg text-muted">{{ __('pentahoot.game_over') }}</p>
        </x-scene>
        </div>
    </div>

    {{-- Tebak Kata and Tebak Gambar: phones mirror the Public View only when the host turned it on
         (E14), with no buttons; players answer out loud. The final podium always shows, as text (E17). --}}
    <div x-data="tebakScreen({ types: ['tebak_kata', 'tebak_gambar'], labels: @js(__('tebak')) })" x-cloak x-show="$store.realtime.screen() === 'live' && game" class="flex grow flex-col">
        <div x-show="view === 'final'" class="flex grow flex-col gap-4">
            <h1 class="font-display text-[22px] font-bold"><span x-text="game?.title"></span> · {{ __('tebak.final') }}</h1>
            <x-podium stand="stand" :score="__('tebak.points')" self="$store.realtime.me?.name" class="pt-2" />
        </div>

        <div x-show="view !== 'final' && !mirrored" class="flex grow flex-col items-center justify-center gap-4 text-center" role="status">
            <x-mascot :size="80" mood="thinking" />
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
                    @foreach (['tebak_kata', 'tebak_gambar'] as $tebakType)
                        <template x-if="game.type === '{{ $tebakType }}'">
                            @include('join.tebak.' . $tebakType)
                        </template>
                    @endforeach
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
