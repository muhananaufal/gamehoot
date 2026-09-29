{{-- F21: runs before first paint to avoid a light flash. Same rule as resolveTheme() in resources/js/theme.js. --}}
<script @if ($nonce = \Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ $nonce }}" @endif>
    (() => {
        let stored = null;
        try {
            stored = localStorage.getItem('pentahoot-theme');
        } catch {}
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.dataset.theme =
            stored === 'light' || stored === 'dark' ? stored : prefersDark ? 'dark' : 'light';
    })();
</script>
