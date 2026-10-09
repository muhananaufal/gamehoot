{{-- F20, T2, U30: shown while the socket is down (the store keeps polling meanwhile), then "Back
     online" for a moment once it returns. Slides in from above and leaves faster. --}}
<div x-data="connectionBanner" x-cloak x-show="back || ['reconnecting', 'offline'].includes($store.realtime.connection)" role="status" aria-live="polite"
    x-transition:enter="scene-in" x-transition:enter-start="scene-from" x-transition:enter-end="scene-at"
    x-transition:leave="scene-out" x-transition:leave-start="scene-at" x-transition:leave-end="scene-gone"
    :class="back ? 'bg-success text-on-success' : 'bg-warning text-on-warning'"
    {{ $attributes->merge(['class' => 'phone-scenes rounded-card px-4 py-3 text-sm font-semibold']) }}>
    <span x-text="back ? @js(__('realtime.connection.back_online')) : $store.realtime.connection === 'offline' ? @js(__('realtime.connection.offline')) : @js(__('realtime.connection.reconnecting'))"></span>
</div>
