{{-- Tebak Gambar on a phone (E9, E14): the 720 px question image, then the answer image below it
     once revealed or once the question ends. --}}
<div class="flex flex-col gap-4">
    <p class="font-display text-[24px] leading-tight font-bold" x-text="question.title"></p>
    <img :src="question.image.small" :alt="question.title" class="w-full rounded-card object-contain" data-test="question-image">
    <template x-if="question.answer_image">
        <figure class="flex flex-col gap-2">
            <figcaption class="text-sm font-semibold text-muted" x-text="labels.answer_image"></figcaption>
            <img :src="question.answer_image.small" :alt="labels.answer_image" class="w-full rounded-card object-contain ring-4 ring-rank-1" data-test="answer-image">
        </figure>
    </template>
</div>
