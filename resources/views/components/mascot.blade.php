@props([
    // Pixel size; Penta is drawn at 64 px and up (the logo stays for smaller marks, E19).
    'size' => 96,
    // U29: idle, wave, happy, cheer, thinking, surprised, sad, sleepy, loading, peek, dizzy, shy.
    'mood' => 'idle',
    // U30: a named Penta only hears reactions sent for its name ({ name, mood }).
    'name' => null,
    // Stroke between the segments: the color of the surface behind Penta.
    'outline' => 'stroke-canvas',
])
{{-- U29: Penta, the logo of E19 come to life (resources/js/mascot.js). Decoration: the page around it
     always says the same in words. Holding it calms the motion of this device (U27, option C). --}}
<span x-data="mascot({ mood: '{{ $mood }}', name: {{ Js::from($name) }}, labels: {{ Js::from(['rest' => __('ui.penta_rest'), 'awake' => __('ui.penta_awake')]) }} })"
    data-mood="{{ $mood }}" @mascot-react.window="react($event.detail)" @click="poke()"
    @pointerdown="press()" @pointerup="release()" @pointerleave="release()" @pointercancel="release()" @contextmenu.prevent
    {{ $attributes->merge(['class' => 'relative inline-flex touch-manipulation select-none']) }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 200 200" class="penta overflow-visible" aria-hidden="true" focusable="false">
        <g data-part="body">
            <g class="{{ $outline }}" stroke-width="7" stroke-linejoin="round">
                <path data-part="segment" d="M100 100 L100 10 L185.6 72.2 Z" class="fill-brand-yellow"></path>
                <path data-part="segment" d="M100 100 L185.6 72.2 L152.9 172.8 Z" class="fill-brand-coral"></path>
                <path data-part="segment" d="M100 100 L152.9 172.8 L47.1 172.8 Z" class="fill-brand-mint"></path>
                <path data-part="segment" d="M100 100 L47.1 172.8 L14.4 72.2 Z" class="fill-brand-blue"></path>
                <path data-part="segment" d="M100 100 L14.4 72.2 L100 10 Z" class="fill-brand-pink"></path>
            </g>
            <g data-part="eye">
                <circle cx="74" cy="98" r="21" class="fill-brand-eye"></circle>
                <circle data-part="pupil" cx="78" cy="101" r="9" class="fill-brand-pupil"></circle>
            </g>
            <g data-part="eye">
                <circle cx="126" cy="98" r="21" class="fill-brand-eye"></circle>
                <circle data-part="pupil" cx="130" cy="101" r="9" class="fill-brand-pupil"></circle>
            </g>
            <g data-part="happy" opacity="0" class="penta-line">
                <path d="M58 104 Q74 82 90 104"></path>
                <path d="M110 104 Q126 82 142 104"></path>
            </g>
            <path data-part="smile" opacity="0" d="M86 132 Q100 148 114 132" class="penta-line"></path>
            <ellipse data-part="oh" opacity="0" cx="100" cy="140" rx="8" ry="10" class="fill-brand-pupil"></ellipse>
        </g>
        <text data-part="zz" opacity="0" x="150" y="30" class="fill-muted font-display font-bold" font-size="28">Zz</text>
    </svg>
    {{-- U27: what holding Penta did, for a moment. --}}
    <span x-show="bubble" x-cloak x-text="bubble" role="status"
        class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-inverse px-3 py-1 text-xs font-semibold whitespace-nowrap text-on-inverse"></span>
</span>
