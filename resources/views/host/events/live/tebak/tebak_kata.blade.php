{{-- Tebak Kata on Live control (E5): the prompt, the answer only hosts see, and boxes to open as hints. --}}
<p class="font-display text-2xl leading-tight font-semibold" x-text="question.prompt"></p>
<p class="text-sm"><span class="font-bold">{{ __('tebak.answer') }}:</span> <span class="font-display tracking-wide" x-text="question.answer"></span></p>
<x-kata-boxes words="question.boxes" can-open="canHint(cell)" open="act('hint', question.id, { box: cell.b })" class="text-[26px]" />
<p x-show="question.status === 'shown'" class="text-sm text-muted">{{ __('tebak.hint_help') }}</p>
