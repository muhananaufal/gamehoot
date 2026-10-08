{{-- O7: one calm page for every HTTP error, readable on a phone and a laptop alike. --}}
@php
    $retry = in_array($status, [419, 429, 503], true);
@endphp
<x-layouts.phone :title="__('errors_page.'.$status.'.title')">
    <div class="flex grow flex-col items-center justify-center gap-4 text-center" role="alert">
        {{-- U32: one mood per error. --}}
        <x-mascot :size="80" :mood="[404 => 'peek', 500 => 'dizzy', 419 => 'sleepy', 503 => 'loading', 403 => 'surprised', 429 => 'dizzy'][$status] ?? 'thinking'" />
        <h1 class="font-display text-[28px] leading-tight font-bold">{{ __('errors_page.'.$status.'.title') }}</h1>
        <p class="max-w-xs text-lg text-muted">{{ __('errors_page.'.$status.'.body') }}</p>
        @if ($retry)
            <x-button :href="url()->current()" class="mt-2">{{ __('errors_page.reload') }}</x-button>
        @endif
        <x-button :href="url('/')" variant="secondary">{{ __('errors_page.home') }}</x-button>
        <p class="text-sm text-muted">{{ __('errors_page.code', ['status' => $status]) }}</p>
    </div>
</x-layouts.phone>
