<x-layouts.host :heading="false" :title="__('accounts.edit_title', ['name' => $account->name])"
    :breadcrumbs="[[__('host.nav.admin'), null], [__('accounts.host_accounts'), route('admin.users.index')], [__('accounts.edit_title', ['name' => $account->name]), null]]">
    {{-- C-7, U13: what the Edit modal shows: a title, fields as wide as the box, buttons at the bottom right. --}}
    <div up-main="modal" class="flex flex-col gap-5 p-6">
        <h2 class="font-display text-title-s font-bold">{{ __('accounts.edit_title', ['name' => $account->name]) }}</h2>
        <form method="POST" action="{{ route('admin.users.update', $account) }}" class="flex flex-col gap-4" novalidate>
            @csrf
            @method('PUT')
            <x-field name="name" :placeholder="__('examples.person_name')" :label="__('accounts.name')" :value="$account->name" autocomplete="off" required maxlength="100" />
            <x-field name="username" :placeholder="__('examples.username')" :label="__('accounts.username')" :value="$account->username" :hint="__('accounts.username_hint')" autocomplete="off" autocapitalize="none" spellcheck="false" required maxlength="30"
                pattern="[a-z0-9._\-]{3,30}" :data-pattern-message="__('accounts.username_rule')" />
            <x-field name="email" :placeholder="__('examples.email')" type="email" :label="__('accounts.email')" :value="$account->email" autocomplete="off" required />
            <div class="flex justify-end gap-2.5 pt-2">
                <x-button variant="secondary" :href="route('admin.users.index')" up-dismiss="">{{ __('ui.cancel') }}</x-button>
                <x-button type="submit">{{ __('accounts.save') }}</x-button>
            </div>
        </form>
    </div>
</x-layouts.host>
