<x-layouts.host :title="__('accounts.add_host')"
    :breadcrumbs="[[__('host.nav.admin'), null], [__('accounts.host_accounts'), route('admin.users.index')], [__('accounts.add_host'), null]]">
    <form method="POST" action="{{ route('admin.users.store') }}" class="flex max-w-lg flex-col gap-4 rounded-card bg-surface p-6" novalidate>
        @csrf
        <x-field name="name" :label="__('accounts.name')" autocomplete="off" required />
        <x-field name="email" type="email" :label="__('accounts.email')" autocomplete="off" required />
        <x-field name="password" type="password" :label="__('accounts.password')" :hint="__('accounts.password_hint')" autocomplete="new-password" required />
        <x-field name="password_confirmation" type="password" :label="__('accounts.password_confirmation')" autocomplete="new-password" required />
        <div class="flex justify-end gap-2.5 pt-2">
            <x-button variant="secondary" :href="route('admin.users.index')">{{ __('ui.cancel') }}</x-button>
            <x-button type="submit">{{ __('accounts.create_account') }}</x-button>
        </div>
    </form>
</x-layouts.host>
