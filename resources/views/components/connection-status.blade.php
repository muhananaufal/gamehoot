{{-- F20, T2: shown while the socket is down; the store keeps polling meanwhile. --}}
<div x-data x-cloak x-show="['reconnecting', 'offline'].includes($store.realtime.connection)" role="status" aria-live="polite"
    {{ $attributes->merge(['class' => 'rounded-card bg-warning px-4 py-3 text-sm font-semibold text-on-warning']) }}>
    <span x-text="$store.realtime.connection === 'offline' ? @js(__('realtime.connection.offline')) : @js(__('realtime.connection.reconnecting'))"></span>
</div>
