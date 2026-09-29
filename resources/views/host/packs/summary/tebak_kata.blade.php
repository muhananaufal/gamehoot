<span class="truncate font-semibold">{{ $question->kata?->prompt }}</span>
<span class="text-sm text-muted">
    <span class="font-display font-semibold tracking-wide text-ink">{{ mb_strtoupper((string) $question->kata?->answer_text) }}</span>
    · {{ trans_choice('packs.points_count', $question->points) }}
</span>
