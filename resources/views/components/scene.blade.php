@props([
    // A JavaScript expression: whether this scene shows.
    'show',
    'as' => 'main',
    // false for the scene that the server draws before scripts run (the lobby).
    'cloak' => true,
])
{{-- U30: one scene of the Public View or a phone. Scenes share one grid cell (scene-stack in
     app.css), so the new one comes in while the old one leaves, without the page jumping. Data
     never waits for this: Alpine shows the new scene at once and only its entrance moves. --}}
<{{ $as }}{{ $cloak ? ' x-cloak' : '' }} x-show="{{ $show }}"
    x-transition:enter="scene-in" x-transition:enter-start="scene-from" x-transition:enter-end="scene-at"
    x-transition:leave="scene-out" x-transition:leave-start="scene-at" x-transition:leave-end="scene-gone"
    {{ $attributes }}>{{ $slot }}</{{ $as }}>
