@props(['name'])
@php
    $paths = [
        'calendar' => '<path d="M4 6h16v14H4zM4 10h16M9 3v4M15 3v4"></path>',
        'layers' => '<path d="M4 7l8-4 8 4-8 4-8-4zM4 12l8 4 8-4M4 17l8 4 8-4"></path>',
        'user' => '<circle cx="12" cy="8" r="4"></circle><path d="M4 21a8 8 0 0116 0"></path>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14"></path>',
        'moon' => '<path d="M20 14.5A8 8 0 019.5 4 8 8 0 1020 14.5z"></path>',
        'sun' => '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>',
        'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"></path><circle cx="12" cy="12" r="3"></circle>',
        'eye-off' => '<path d="M3 3l18 18M10.6 5.1A9.8 9.8 0 0112 5c6.4 0 10 7 10 7a17 17 0 01-3.1 3.9M6.6 6.6A17 17 0 002 12s3.6 7 10 7a9.6 9.6 0 005.4-1.6M9.9 9.9a3 3 0 004.2 4.2"></path>',
        'chevron-left' => '<path d="M15 6l-6 6 6 6"></path>',
        'chevron-right' => '<path d="M9 6l6 6-6 6"></path>',
        'pencil' => '<path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4"></path>',
        'logout' => '<path d="M14 4h5v16h-5M10 8l-4 4 4 4M6 12h10"></path>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"></path>',
        'settings' => '<circle cx="12" cy="12" r="3"></circle><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"></path>',
        'broadcast' => '<circle cx="12" cy="12" r="2"></circle><path d="M8.5 15.5a5 5 0 010-7M15.5 8.5a5 5 0 010 7M5.6 18.4a9 9 0 010-12.8M18.4 5.6a9 9 0 010 12.8"></path>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"></path>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"></path>',
        'more' => '<circle cx="5" cy="12" r="1.2"></circle><circle cx="12" cy="12" r="1.2"></circle><circle cx="19" cy="12" r="1.2"></circle>',
        'search' => '<circle cx="11" cy="11" r="6.5"></circle><path d="M16 16l4.5 4.5"></path>',
        'sort' => '<path d="M8 4v16M4 8l4-4 4 4M16 20V4M12 16l4 4 4-4"></path>',
        'grip' => '<circle cx="9" cy="6" r="1"></circle><circle cx="15" cy="6" r="1"></circle><circle cx="9" cy="12" r="1"></circle><circle cx="15" cy="12" r="1"></circle><circle cx="9" cy="18" r="1"></circle><circle cx="15" cy="18" r="1"></circle>',
        'plus' => '<path d="M12 5v14M5 12h14"></path>',
        'copy' => '<rect x="9" y="9" width="11" height="11" rx="2"></rect><path d="M5 15V5a1 1 0 011-1h10"></path>',
        'sidebar' => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M9 4v16"></path>',
        'refresh' => '<path d="M20 11a8 8 0 00-14.9-4M4 3v4h4M4 13a8 8 0 0014.9 4M20 21v-4h-4"></path>',
        'unlink' => '<path d="M9 17H7a5 5 0 010-10h2M15 7h2a5 5 0 014 8M8 12h3M3 3l18 18"></path>',
        'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"></path>',
        'arrow-down' => '<path d="M12 5v14M6 13l6 6 6-6"></path>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"></path>',
    ];
@endphp
<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
    stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>{!! $paths[$name] !!}</svg>
