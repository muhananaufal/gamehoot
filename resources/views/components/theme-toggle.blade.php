{{-- U17: one theme switch for the host panel and the sign in page. The icon shows where a press
     goes: sun while dark, moon while light (resources/js/theme.js). --}}
<button type="button" x-data="themeToggle" @click="toggle()" aria-label="{{ __('host.nav.switch_theme') }}" title="{{ __('host.nav.switch_theme') }}"
    {{ $attributes->merge(['class' => 'flex size-11 shrink-0 items-center justify-center rounded-control']) }}>
    <span x-show="dark" class="flex">
        <x-icon name="sun" />
    </span>
    <span x-show="! dark" class="flex">
        <x-icon name="moon" />
    </span>
</button>
