<x-layouts.host :heading="false" :title="__('accounts.add_host')"
    :breadcrumbs="[[__('host.nav.admin'), null], [__('accounts.host_accounts'), route('admin.users.index')], [__('accounts.add_host'), null]]">
    {{-- U13: what the Add host modal shows: a title, fields as wide as the box, buttons at the bottom right. --}}
    <div up-main="modal" class="flex flex-col gap-5 p-6">
        <h2 class="font-display text-[22px] font-bold">{{ __('accounts.add_host') }}</h2>
        <form method="POST" action="{{ route('admin.users.store') }}" class="flex flex-col gap-4" novalidate>
            @csrf
            <x-forms.new-host-fields />
            <div class="flex justify-end gap-2.5 pt-2">
                <x-button variant="secondary" :href="route('admin.users.index')" up-dismiss="">{{ __('ui.cancel') }}</x-button>
                <x-button type="submit">{{ __('accounts.create_account') }}</x-button>
            </div>
        </form>
    </div>
</x-layouts.host>
