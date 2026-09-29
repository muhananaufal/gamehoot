<x-layouts.auth :title="__('accounts.sign_in_title')">
    <h1 class="font-display text-[26px] font-bold">{{ __('accounts.sign_in_title') }}</h1>

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4" novalidate>
        @csrf
        <x-field name="email" type="email" :label="__('accounts.email')" autocomplete="username" required autofocus />
        <x-field name="password" type="password" :label="__('accounts.password')" autocomplete="current-password" required />
        <x-button type="submit" class="mt-1.5 h-12 font-display text-[19px] font-semibold">{{ __('accounts.sign_in') }}</x-button>
    </form>

    <p class="text-sm text-muted">{{ __('accounts.no_self_service') }}</p>
    <p class="text-sm text-muted">{{ __('accounts.joining_hint') }}</p>
</x-layouts.auth>
