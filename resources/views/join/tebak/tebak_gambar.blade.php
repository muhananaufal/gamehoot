{{-- Tebak Gambar on a phone (E9, E14): the 720 px question image, then the answer image below it
     once revealed or once the question ends. --}}
<div class="flex flex-col gap-4" x-data="{ loaded: [] }">
    <p class="font-display text-[24px] leading-tight font-bold" x-text="question.title"></p>
    {{-- U30: fades in once loaded; the answer opens like a curtain. --}}
    <img :src="question.image.small" :alt="question.title" class="fade-load w-full rounded-card object-contain" data-test="question-image"
        @load="loaded.push($el.getAttribute('src'))" :class="loaded.includes(question.image.small) && 'is-loaded'">
    <template x-if="question.answer_image">
        <figure class="flex flex-col gap-2">
            <figcaption class="text-sm font-semibold text-muted" x-text="labels.answer_image"></figcaption>
            <img :src="question.answer_image.small" :alt="labels.answer_image" class="curtain-load w-full rounded-card object-contain ring-4 ring-rank-1" data-test="answer-image"
                @load="loaded.push($el.getAttribute('src'))" :class="loaded.includes(question.answer_image.small) && 'is-loaded'">
        </figure>
    </template>
</div>
