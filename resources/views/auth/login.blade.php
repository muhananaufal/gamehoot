<x-layouts.auth :title="__('accounts.sign_in_title')">
    <h1 class="font-display text-[26px] font-bold">{{ __('accounts.sign_in_title') }}</h1>

    {{-- U19: sign in leaves every check to the server. --}}
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4" novalidate data-no-check>
        @csrf
        <x-field name="login" :label="__('accounts.login')" :placeholder="__('accounts.login')" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus :live="false" />
        <x-field name="password" type="password" :label="__('accounts.password')" autocomplete="current-password" required />
        <x-button type="submit" class="mt-1.5 h-12 font-display text-[19px] font-semibold">{{ __('accounts.sign_in') }}</x-button>
    </form>

    <p class="text-sm text-muted">{{ __('accounts.no_self_service') }}</p>
    <p class="text-sm text-muted">{{ __('accounts.joining_hint') }}</p>
</x-layouts.auth>
