@inject('mediaUrls', 'App\Media\MediaUrls')
@php($image = $question->gambar?->questionImage)
<span class="flex min-w-0 items-center gap-3">
    @if ($image)
        <img src="{{ $mediaUrls->sizes($image)['small'] }}" alt="" loading="lazy" class="h-10 w-16 shrink-0 rounded-[6px] bg-subtle object-cover">
    @endif
    <span class="flex min-w-0 flex-col">
        <span class="truncate font-semibold">{{ $question->gambar?->title }}</span>
        <span class="text-sm text-muted">
            <span class="font-semibold text-ink">{{ $question->gambar?->answer_text }}</span>
            · {{ trans_choice('packs.points_count', $question->points) }}
        </span>
    </span>
</span>
