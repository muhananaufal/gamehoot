{{-- Tebak Gambar on Live control (E7, E9): the title, the answer and both images for the host, and
     Reveal of the answer image. After Reveal, Skip is off. --}}
<p class="font-display text-2xl leading-tight font-semibold" x-text="question.title"></p>
<p class="text-sm"><span class="font-bold">{{ __('tebak.answer') }}:</span> <span class="font-semibold" x-text="question.answer"></span></p>
<div class="grid gap-3 sm:grid-cols-2">
    <img :src="question.image.small" :alt="question.title" class="w-full rounded-control border border-line-soft object-contain">
    <figure class="flex flex-col gap-1.5">
        <img :src="question.answer_image?.small" :alt="labels.answer_image" class="w-full rounded-control border border-line-soft object-contain"
            :class="question.revealed ? 'ring-4 ring-rank-1' : 'opacity-80'">
        <figcaption class="text-xs text-muted" x-text="question.revealed ? labels.revealed : labels.answer_image_host"></figcaption>
    </figure>
</div>
<div x-show="canReveal">
    <x-confirm-dialog :title="__('tebak.reveal_title')" :trigger="__('tebak.reveal')" trigger-variant="primary">
        <p class="text-[15px] text-muted">{{ __('tebak.reveal_help') }}</p>
        {{-- Closes the dialog itself, like the winner confirmation: see live/tebak.blade.php. --}}
        <form method="dialog" class="flex justify-end gap-2.5 pt-1.5">
            <x-button variant="secondary" type="submit">{{ __('ui.cancel') }}</x-button>
            <x-button type="button" data-test="confirm-reveal" x-bind:disabled="busy" @click="$el.closest('dialog').close(); act('reveal')">{{ __('tebak.reveal') }}</x-button>
        </form>
    </x-confirm-dialog>
</div>
