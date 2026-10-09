<x-layouts.auth :title="__('accounts.sign_in_title')">
    {{-- U32: Penta follows the caret, covers its eyes for the password, peeks when it is shown and
         is sad after a failed sign in. --}}
    <x-mascot :size="72" :mood="$errors->any() ? 'sad' : 'idle'" name="login" class="self-start" />
    <h1 class="font-display text-[26px] font-bold">{{ __('accounts.sign_in_title') }}</h1>

    {{-- U19: sign in leaves every check to the server. --}}
    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4" novalidate data-no-check
        x-data="loginPenta" @focusin="follow($event.target)" @input="follow($event.target)" @keyup="follow($event.target)"
        @focusout="$nextTick(() => follow(document.activeElement))"
        @click="$event.target.closest('button[aria-pressed]') && $nextTick(() => follow($event.target.closest('div').querySelector('input')))">
        @csrf
        <x-field name="login" :label="__('accounts.login')" :placeholder="__('examples.username_or_email')" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus :live="false" />
        <x-field name="password" :placeholder="__('examples.password')" type="password" :label="__('accounts.password')" autocomplete="current-password" required />
        <x-button type="submit" class="mt-1.5 h-12 font-display text-[19px] font-semibold">{{ __('accounts.sign_in') }}</x-button>
    </form>

    <p class="text-sm text-muted">{{ __('accounts.no_self_service') }}</p>
    <p class="text-sm text-muted">{{ __('accounts.joining_hint') }}</p>
</x-layouts.auth>
