{{-- Tebak Kata on the projector (E5): the prompt and its boxes, with the legend while it is open. --}}
<div class="flex flex-col items-center gap-[4vh]">
    <p class="font-display text-[1.8em] leading-tight font-semibold text-balance" x-text="question.prompt"></p>
    <x-kata-boxes words="question.boxes ?? []" class="text-[clamp(28px,4vw,72px)]" />
    <div x-show="view === 'question'" class="flex gap-[2vw] text-[0.8em] text-muted">
        <span class="flex items-center gap-2"><span class="size-[0.9em] rounded-sm bg-accent" aria-hidden="true"></span>{{ __('tebak.legend_initial') }}</span>
        <span class="flex items-center gap-2"><span class="size-[0.9em] rounded-sm bg-rank-1" aria-hidden="true"></span>{{ __('tebak.legend_hint') }}</span>
    </div>
</div>
