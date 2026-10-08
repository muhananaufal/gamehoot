<x-layouts.host :heading="false" :title="__('accounts.reset_password_for', ['name' => $account->name])"
    :breadcrumbs="[[__('host.nav.admin'), null], [__('accounts.host_accounts'), route('admin.users.index')], [__('accounts.reset_password'), null]]">
    {{-- C-7, U13: what the Reset password modal shows: a title, fields as wide as the box, buttons at the bottom right. --}}
    <div up-main="modal" class="flex flex-col gap-5 p-6">
        <div class="flex flex-col gap-1">
            <h2 class="font-display text-[22px] font-bold">{{ __('accounts.reset_password_for', ['name' => $account->name]) }}</h2>
            <p class="text-sm text-muted">{{ __('accounts.reset_password_help') }}</p>
        </div>
        <form method="POST" action="{{ route('admin.users.password.update', $account) }}" class="flex flex-col gap-4" novalidate>
            @csrf
            @method('PUT')
            <x-field name="password" :placeholder="__('examples.new_password')" type="password" :label="__('accounts.new_password')" :hint="__('accounts.password_hint')" autocomplete="new-password" required minlength="12" pattern="(?=.*[A-Za-z])(?=.*[0-9]).*" :data-pattern-message="__('accounts.password_hint')" />
            <x-field name="password_confirmation" :placeholder="__('examples.password_again')" type="password" :label="__('accounts.password_confirmation')" autocomplete="new-password" required data-same="password" />
            <div class="flex justify-end gap-2.5 pt-2">
                <x-button variant="secondary" :href="route('admin.users.index')" up-dismiss="">{{ __('ui.cancel') }}</x-button>
                <x-button type="submit">{{ __('accounts.save_password') }}</x-button>
            </div>
        </form>
    </div>
</x-layouts.host>
