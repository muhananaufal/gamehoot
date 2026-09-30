@props(['event', 'audience'])
{{-- F20: settings for the shared realtime store, read by resources/js/app.js. --}}
<script type="application/json" id="realtime-config">@json(app(\App\Realtime\ClientConfig::class)->for($event, $audience))</script>
