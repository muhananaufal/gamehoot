{{-- Tebak Gambar on the projector (E9, E14): the 1920 px question image, then the answer image
     beside it once revealed or once the question ends. --}}
<div class="flex w-full flex-col items-center gap-[3vh]">
    <p class="font-display text-[1.6em] leading-tight font-semibold text-balance" x-text="question.title"></p>
    <div class="flex w-full items-start justify-center gap-[2vw]">
        <img :src="question.image.large" :alt="question.title" data-test="question-image"
            class="max-h-[58vh] min-w-0 rounded-panel object-contain" :class="question.answer_image ? 'max-w-[46vw]' : 'max-w-[88vw]'">
        <template x-if="question.answer_image">
            <figure class="flex max-w-[46vw] min-w-0 flex-col items-center gap-[1vh]">
                <img :src="question.answer_image.large" :alt="labels.answer_image" data-test="answer-image"
                    class="max-h-[52vh] rounded-panel object-contain ring-[0.4vw] ring-rank-1">
                <figcaption class="text-[0.8em] font-semibold text-muted" x-text="labels.answer_image"></figcaption>
            </figure>
        </template>
    </div>
</div>
