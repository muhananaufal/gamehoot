<x-layouts.host :title="__('accounts.host_accounts')"
    :breadcrumbs="[[__('host.nav.admin'), null], [__('accounts.host_accounts'), null]]">
    <x-slot:actions>
        <x-button :href="route('admin.users.create')">{{ __('accounts.add_host') }}</x-button>
    </x-slot:actions>

    <div class="overflow-x-auto rounded-card bg-surface">
        <table class="w-full min-w-[720px] text-left text-[15px]">
            <thead class="border-b border-line-soft text-xs font-bold tracking-[0.06em] text-muted uppercase">
                <tr>
                    <th scope="col" class="px-5 py-3">{{ __('accounts.name') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('accounts.email') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('accounts.role') }}</th>
                    <th scope="col" class="px-4 py-3">{{ __('accounts.status') }}</th>
                    <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('accounts.status') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $account)
                    <tr class="border-b border-line-soft last:border-0">
                        <td class="px-5 py-3.5 font-bold">{{ $account->name }}</td>
                        <td class="px-4 py-3.5 text-muted">{{ $account->email }}</td>
                        <td class="px-4 py-3.5">{{ $account->is_super_admin ? __('accounts.role_super_admin') : __('accounts.role_host') }}</td>
                        <td class="px-4 py-3.5">
                            @if ($account->disabled_at)
                                <x-chip>{{ __('accounts.status_disabled') }}</x-chip>
                            @else
                                <x-chip tone="success" dot>{{ __('accounts.status_active') }}</x-chip>
                            @endif
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-4 text-sm font-semibold">
                                @if ($account->is(auth()->user()))
                                    <span class="text-muted">{{ __('accounts.you') }}</span>
                                @else
                                    @unless ($account->disabled_at)
                                        <x-button variant="link" :href="route('admin.users.password.edit', $account)">{{ __('accounts.reset_password') }}</x-button>
                                    @endunless
                                    <form method="POST" action="{{ route('admin.users.status.update', $account) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="disabled" value="{{ $account->disabled_at ? '0' : '1' }}">
                                        <x-button type="submit" :variant="$account->disabled_at ? 'link' : 'link-destructive'">
                                            {{ $account->disabled_at ? __('accounts.enable') : __('accounts.disable') }}
                                        </x-button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="grid gap-5 md:grid-cols-2">
        <section class="flex flex-col gap-2 rounded-card bg-surface p-5">
            <h2 class="font-display text-xl font-semibold">{{ __('accounts.reset_password') }}</h2>
            <p class="text-sm text-muted">{{ __('accounts.reset_password_help') }}</p>
        </section>
        <section class="flex flex-col gap-2 rounded-card bg-surface p-5">
            <h2 class="font-display text-xl font-semibold">{{ __('accounts.status_disabled') }}</h2>
            <p class="text-sm text-muted">{{ __('accounts.disabled_help') }}</p>
        </section>
    </div>
</x-layouts.host>
