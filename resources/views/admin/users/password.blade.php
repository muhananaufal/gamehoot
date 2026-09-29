<x-layouts.host :title="__('accounts.reset_password_for', ['name' => $account->name])"
    :breadcrumbs="[[__('host.nav.admin'), null], [__('accounts.host_accounts'), route('admin.users.index')], [__('accounts.reset_password'), null]]">
    <form method="POST" action="{{ route('admin.users.password.update', $account) }}" class="flex max-w-lg flex-col gap-4 rounded-card bg-surface p-6" novalidate>
        @csrf
        @method('PUT')
        <h1 class="font-display text-xl font-semibold">{{ __('accounts.reset_password_for', ['name' => $account->name]) }}</h1>
        <p class="text-sm text-muted">{{ __('accounts.reset_password_help') }}</p>
        <x-field name="password" type="password" :label="__('accounts.new_password')" :hint="__('accounts.password_hint')" autocomplete="new-password" required />
        <x-field name="password_confirmation" type="password" :label="__('accounts.password_confirmation')" autocomplete="new-password" required />
        <div class="flex justify-end gap-2.5 pt-2">
            <x-button variant="secondary" :href="route('admin.users.index')">{{ __('ui.cancel') }}</x-button>
            <x-button type="submit">{{ __('accounts.save_password') }}</x-button>
        </div>
    </form>
</x-layouts.host>
