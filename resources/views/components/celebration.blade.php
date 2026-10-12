@props([
    // true on the Public View: canvas confetti and the host's Screen animations setting.
    'projector' => false,
])
{{-- U31: the celebration layer. Pages announce a key moment with $dispatch('celebrate', { key });
     Penta pops in to cheer, and confetti falls unless the mode is reduced (U27) or, on the Public
     View, the host turned Screen animations off. Decorative: screen readers get the result itself. --}}
<div x-data="celebration({ projector: {{ $projector ? 'true' : 'false' }} })" @celebrate.window="play($event.detail)"
    class="pointer-events-none fixed inset-0 z-40 overflow-hidden" aria-hidden="true">
    <div x-ref="confetti" class="absolute inset-0"></div>
    <div x-cloak x-show="cheering"
        x-transition:enter="scene-in" x-transition:enter-start="scene-from" x-transition:enter-end="scene-at"
        x-transition:leave="scene-out" x-transition:leave-start="scene-at" x-transition:leave-end="scene-gone"
        class="{{ $projector ? 'absolute right-[4vw] bottom-[6vh]' : 'phone-scenes absolute right-4 bottom-6' }}">
        <x-mascot :size="$projector ? 160 : 72" mood="cheer" name="celebration" />
    </div>
</div>
