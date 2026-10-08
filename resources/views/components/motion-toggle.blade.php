{{-- U27: the Reduce motion switch for this device, beside the theme switch. The icon shows the
     current mode (sparkles, crossed out while reduced); the label says what a press does. --}}
<button type="button" x-data @click="$store.motion.toggle()" :aria-pressed="$store.motion.reduced.toString()"
    aria-pressed="false" aria-label="{{ __('ui.reduce_motion') }}" title="{{ __('ui.reduce_motion') }}"
    :aria-label="$store.motion.reduced ? @js(__('ui.allow_motion')) : @js(__('ui.reduce_motion'))"
    :title="$store.motion.reduced ? @js(__('ui.allow_motion')) : @js(__('ui.reduce_motion'))"
    {{ $attributes->merge(['class' => 'flex size-11 shrink-0 items-center justify-center rounded-control']) }}>
    <span x-show="! $store.motion.reduced" class="flex">
        <x-icon name="sparkles" />
    </span>
    <span x-show="$store.motion.reduced" x-cloak class="flex">
        <x-icon name="sparkles-off" />
    </span>
</button>
