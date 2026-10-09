{{-- U27: the Reduce motion switch for this device, beside the theme switch. The icon shows the
     current mode (sparkles, crossed out while reduced); the label says what a press does. --}}
<button type="button" x-data @click="$store.motion.toggle()" :aria-pressed="$store.motion.reduced.toString()"
    aria-pressed="false" aria-label="{{ __('ui.reduce_motion') }}" title="{{ __('ui.reduce_motion') }}"
    :aria-label="$store.motion.reduced ? @js(__('ui.allow_motion')) : @js(__('ui.reduce_motion'))"
    :title="$store.motion.reduced ? @js(__('ui.allow_motion')) : @js(__('ui.reduce_motion'))"
    {{ $attributes->merge(['class' => 'flex size-11 shrink-0 items-center justify-center rounded-control']) }}>
    {{-- U5: icons drawn once in CSS, so the 500-name join page stays light (A3). --}}
    <span x-show="! $store.motion.reduced" data-icon="sparkles" aria-hidden="true"></span>
    <span x-show="$store.motion.reduced" x-cloak data-icon="sparkles-off" aria-hidden="true"></span>
</button>
