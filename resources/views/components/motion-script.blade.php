@props([
    // U27, U31: false on the Public View, which follows the device setting (and later the event's
    // Screen animations setting), never a choice the host made in the panel on the same browser.
    'remember' => true,
])
{{-- U27: runs before first paint, so nothing moves before the mode is known. Same rule as
     resolveMotion() in resources/js/motion.js; CSS reads <html data-motion>. --}}
<script @if ($nonce = \Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ $nonce }}" @endif>
    (() => {
        let stored = null;
        @if ($remember)
            try {
                stored = localStorage.getItem('pentahoot-motion');
            } catch {}
        @endif
        const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        document.documentElement.dataset.motion =
            stored === 'full' || stored === 'reduced' ? stored : prefersReduced ? 'reduced' : 'full';
    })();
</script>
