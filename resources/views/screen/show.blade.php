@php
    $withoutScheme = fn (string $url): string => (string) preg_replace('#^https?://#', '', $url);
@endphp
{{-- F23, E18: the projector screen, sized for 16:9 screens of any resolution. Lobby while no
     game runs; the Pentahoot question, reveal and podium (E2, E15, E17, E20) or the Tebak Kata
     boxes, leaderboard and final podium (E5, E11, E12) while one does. --}}
<x-layouts.screen :title="__('realtime.screen.title') . ' · ' . $event->name" :event="$event">
    <x-realtime :event="$event" :audience="\App\Enums\Audience::Public" />
    <div x-data="pentahootScreen({
            labels: @js(__('pentahoot')),
            reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        })"
        class="flex min-h-dvh flex-col gap-[4vh] px-[5vw] py-[5vh]">
        <header class="flex items-center justify-between gap-6">
            <div class="flex min-w-0 items-center gap-[1.2vw]">
                <x-logo :size="48" outline="stroke-canvas" class="text-[clamp(20px,2.2vw,34px)]" />
                <span x-cloak x-show="question && !finished" class="truncate text-[clamp(16px,1.7vw,26px)] text-muted"
                    x-text="question ? label('question_of', { N: question.number, TOTAL: game.count }) : ''"></span>
            </div>
            <span class="truncate text-[clamp(18px,1.8vw,28px)] text-muted" x-text="$store.realtime.snapshot?.event.name">{{ $event->name }}</span>
        </header>

        <x-connection-status class="text-[clamp(14px,1.2vw,20px)]" />

        {{-- F23, E18: the lobby, while no game runs. --}}
        <main x-show="$store.realtime.screen() === 'live' && !game" class="flex grow flex-wrap items-center justify-center gap-[5vw] text-center">
            <div class="flex min-w-0 flex-col items-center gap-[3vh]">
                <p class="text-[clamp(20px,2.4vw,40px)] text-muted">{{ __('realtime.screen.join_at') }}</p>
                <p class="font-display text-[clamp(36px,5vw,96px)] leading-tight font-bold break-all"
                    x-text="$store.realtime.snapshot?.event.link.replace(/^https?:\/\//, '')">{{ $withoutScheme(route('join.index', $event)) }}</p>
            </div>
            {{-- E18: dark modules on a light card in both themes, so phone cameras can read it. --}}
            <div data-test="join-qr" class="relative w-[min(32vh,28vw)] shrink-0 rounded-panel bg-brand-eye p-[1.2vh] text-brand-pupil">
                <div class="[&>svg]:h-auto [&>svg]:w-full" role="img" aria-label="{{ __('realtime.screen.qr') }}">{!! \App\Support\JoinQrCode::svg(route('join.index', $event)) !!}</div>
                <span class="absolute inset-0 m-auto flex size-[22%] items-center justify-center rounded-card bg-brand-eye">
                    <x-logo :size="48" :wordmark="false" outline="stroke-brand-eye" class="size-[80%] [&>svg]:size-full" />
                </span>
            </div>
        </main>

        {{-- Pentahoot: no question open. --}}
        <main x-cloak x-show="$store.realtime.screen() === 'live' && isPentahoot && !finished && (phase === 'none' || phase === 'ready' || phase === 'done')"
            class="flex grow flex-col items-center justify-center gap-[3vh] text-center">
            <p class="font-display text-[clamp(36px,5vw,88px)] leading-tight font-bold" x-text="game?.title"></p>
            <p class="text-[clamp(20px,2.4vw,40px)] text-muted">{{ __('pentahoot.next_soon') }}</p>
        </main>

        {{-- G7, E12: a finished Pentahoot game stays on screen until the next game starts. --}}
        <main x-cloak x-show="$store.realtime.screen() === 'live' && isPentahoot && finished"
            class="flex grow flex-col items-center justify-center gap-[3vh] text-center">
            <p class="font-display text-[clamp(36px,5vw,88px)] leading-tight font-bold" x-text="game?.title"></p>
            <p class="text-[clamp(20px,2.4vw,40px)] text-muted">{{ __('pentahoot.game_over') }}</p>
        </main>

        {{-- E3, E15: the Pentahoot question, the countdown and how many answered. --}}
        <main x-cloak x-show="$store.realtime.screen() === 'live' && isPentahoot && !finished && (phase === 'live' || phase === 'closed')"
            class="flex grow items-center gap-[4vw]">
            <p class="grow font-display text-[clamp(36px,4.6vw,88px)] leading-tight font-semibold text-balance" x-text="question?.prompt"></p>
            <x-countdown seconds="seconds" class="size-[min(36vh,22vw)] text-[clamp(18px,1.8vw,30px)] [&>span:first-child]:text-[4.5em]" />
        </main>

        {{-- E2, E4, E17, E20: the reveal from the lowest rank up, at most ten rows, then the podium. --}}
        <main x-cloak x-show="$store.realtime.screen() === 'live' && isPentahoot && !finished && phase === 'revealed'"
            class="flex grow flex-col gap-[3vh] text-[clamp(18px,2vw,34px)]">
            <div class="flex items-baseline justify-between gap-6">
                <p class="font-display text-[1.4em] leading-tight font-semibold" x-text="question?.prompt"></p>
                <p class="shrink-0 text-muted" x-text="$store.realtime.count(labels.votes_total, question?.total_votes ?? 0)"></p>
            </div>
            <p x-show="results.length === 0" class="flex grow items-center justify-center font-display text-[2em] font-bold">{{ __('pentahoot.no_votes') }}</p>
            <x-rank-list x-show="results.length > 0 && !final" rows="rows" shown="isShown(row)" />
            <x-podium x-show="results.length > 0 && final" stand="stand" class="grow justify-end" />
        </main>

        {{-- Tebak Kata and Tebak Gambar (E5, E9, E11, E12, E17). Only the question itself differs per game. --}}
        <div x-data="tebakScreen({ types: ['tebak_kata', 'tebak_gambar'], labels: @js(__('tebak')) })" x-show="$store.realtime.screen() === 'live' && game" class="contents">
            <main x-cloak x-show="view === 'next'" class="flex grow flex-col items-center justify-center gap-[3vh] text-center">
                <p class="font-display text-[clamp(36px,5vw,88px)] leading-tight font-bold" x-text="game?.title"></p>
                <p class="text-[clamp(20px,2.4vw,40px)] text-muted">{{ __('tebak.next_soon') }}</p>
            </main>

            <main x-cloak x-show="view === 'question' || view === 'won' || view === 'surrendered'"
                class="flex grow flex-col items-center justify-center gap-[4vh] text-center text-[clamp(18px,2vw,34px)]">
                <p x-show="view === 'question'" class="text-muted">{{ __('tebak.raise_hand') }}</p>
                @foreach (['tebak_kata', 'tebak_gambar'] as $tebakType)
                    <template x-if="game?.type === '{{ $tebakType }}' && question">
                        @include('screen.tebak.' . $tebakType)
                    </template>
                @endforeach
                <p x-show="view === 'won'" class="rounded-panel bg-rank-1 px-[2vw] py-[1.5vh] font-display text-[1.4em] font-bold text-on-rank-1">
                    <span>{{ __('tebak.winner') }}:</span> <span x-text="question?.winner"></span>
                </p>
                <p x-show="view === 'surrendered'" class="font-display text-[1.3em] font-semibold text-muted">{{ __('tebak.no_winner') }}</p>
            </main>

            {{-- E11: the leaderboard after a win, with each name's move. --}}
            <main x-cloak x-show="view === 'leaderboard'" class="flex grow flex-col gap-[3vh] text-[clamp(18px,2vw,34px)]">
                <p class="font-display text-[1.6em] font-bold">{{ __('tebak.leaderboard') }}</p>
                <x-rank-list rows="board.map((row) => ({ ...row, votes: row.points }))" note="moveLabel(row)" />
                <p class="text-[0.7em] text-muted">{{ __('tebak.tie_note') }}</p>
            </main>

            {{-- E12, E17: the final podium stays until the next game starts (G7). --}}
            <main x-cloak x-show="view === 'final'" class="flex grow flex-col gap-[3vh] text-[clamp(18px,2vw,34px)]">
                <p class="text-center font-display text-[1.6em] font-bold"><span x-text="game?.title"></span> · {{ __('tebak.final') }}</p>
                <x-podium stand="stand" class="grow justify-end" />
            </main>
        </div>

        <x-live-status :event="$event" class="[&_h1]:text-[clamp(28px,4vw,64px)] [&_p]:text-[clamp(18px,2vw,32px)]" />

        <footer x-show="$store.realtime.screen() === 'live' && (!game || (isPentahoot && !finished && (phase === 'live' || phase === 'closed')))"
            class="flex items-center justify-between gap-6 rounded-panel bg-surface px-[2.5vw] py-[2.5vh] text-[clamp(18px,2vw,32px)]">
            <span x-text="game ? labels.answer_on_phone : @js(__('realtime.screen.waiting'))">{{ __('realtime.screen.waiting') }}</span>
            <span class="font-display font-semibold tabular-nums" aria-live="polite"
                x-text="game ? label('answered_of', { N: game.answered ?? 0, TOTAL: $store.realtime.snapshot?.lobby.joined ?? 0 }) : $store.realtime.count(@js(__('realtime.screen.joined')), $store.realtime.snapshot?.lobby.joined ?? 0)"></span>
        </footer>
    </div>
</x-layouts.screen>
