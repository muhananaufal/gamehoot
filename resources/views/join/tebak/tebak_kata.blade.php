{{-- Tebak Kata on a phone (E5, E14): the prompt and its boxes. --}}
<div class="flex flex-col gap-5">
    <p class="font-display text-[24px] leading-tight font-bold" x-text="question.prompt"></p>
    <x-kata-boxes words="question.boxes" class="text-[26px]" />
</div>
