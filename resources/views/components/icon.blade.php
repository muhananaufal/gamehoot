@props(['name'])
@php
    $paths = [
        'calendar' => '<path d="M4 6h16v14H4zM4 10h16M9 3v4M15 3v4"></path>',
        'layers' => '<path d="M4 7l8-4 8 4-8 4-8-4zM4 12l8 4 8-4M4 17l8 4 8-4"></path>',
        'user' => '<circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0116 0"></path>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14"></path>',
        'moon' => '<path d="M20 14.5A8 8 0 019.5 4 8 8 0 1020 14.5z"></path>',
        'logout' => '<path d="M14 4h5v16h-5M10 8l-4 4 4 4M6 12h10"></path>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"></path>',
        'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"></path>',
        'broadcast' => '<circle cx="12" cy="12" r="2"></circle><path d="M8.5 15.5a5 5 0 010-7M15.5 8.5a5 5 0 010 7M5.6 18.4a9 9 0 010-12.8M18.4 5.6a9 9 0 010 12.8"></path>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"></path>',
    ];
@endphp
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>{!! $paths[$name] !!}</svg>
