@props([
    'title',
    // F21: null follows the viewer's choice or system setting; 'dark' or 'light' is fixed (Public View).
    'theme' => null,
    // U27: false where only the device setting counts (the Public View, U31).
    'rememberMotion' => true,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @if ($theme) data-theme="{{ $theme }}" @endif @unless ($rememberMotion) data-motion-device-only @endunless>
<head>
    {{-- U18: Unpoly reads the page nonce here and gives it to scripts in new fragments. --}}
    {{-- U19: the messages of the checks made before a form is sent, in the server's words. --}}
    <meta name="form-messages" content="{{ json_encode(['required' => __('validation.required'), 'max' => __('validation.max.string'), 'min' => __('validation.min.string'), 'between' => __('validation.between.numeric'), 'same' => __('validation.same'), 'email' => __('validation.email')]) }}">
    <meta name="csp-nonce" content="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @if (! $theme)
        <x-theme-script />
    @endif
    <x-motion-script :remember="$rememberMotion" />
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{ $attributes->merge(['class' => 'min-h-dvh antialiased']) }}>
    {{ $slot }}
</body>
</html>
