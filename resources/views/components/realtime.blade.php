@props(['event', 'audience', 'me' => null, 'counters' => false])
{{-- F20: settings for the shared realtime store, read by resources/js/app.js. F25: the Public View
     passes counters; host pages always follow them, phones never. @json splits its argument at
     commas, so the config is built first. --}}
@php($realtimeConfig = app(\App\Realtime\ClientConfig::class)->for($event, $audience, $me, $counters))
<script type="application/json" id="realtime-config">@json($realtimeConfig)</script>
