@props([
    // U17, A3: the phone draws its icons in CSS, so the 500-name join page stays light.
    'css' => false,
])
{{-- U17: one theme switch for the host panel, the sign in page and the phone header. The icon shows
     where a press goes: sun while dark, moon while light (resources/js/theme.js). --}}
<button type="button" x-data="themeToggle" @click="toggle()" aria-label="{{ __('host.nav.switch_theme') }}" title="{{ __('host.nav.switch_theme') }}"
    {{ $attributes->merge(['class' => 'flex size-11 shrink-0 items-center justify-center rounded-control']) }}>
    @if ($css)
        <span x-show="dark" data-icon="sun" aria-hidden="true"></span>
        <span x-show="! dark" x-cloak data-icon="moon" aria-hidden="true"></span>
    @else
        <span x-show="dark" class="flex">
            <x-icon name="sun" />
        </span>
        <span x-show="! dark" class="flex">
            <x-icon name="moon" />
        </span>
    @endif
</button>
