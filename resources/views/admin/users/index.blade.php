@php
    // U4, U5, C-7: confirmations and the row menu are sent once; each row button only carries
    // its id, name and which actions apply ("off" = shown switched off with its reason).
    $confirmTexts = [
        'disable' => ['title' => __('accounts.disable_title', ['name' => '__N__']), 'body' => __('accounts.disable_confirm'), 'label' => __('accounts.disable'), 'method' => 'PUT', 'tone' => 'destructive', 'data' => ['disabled' => '1']],
        'enable' => ['title' => __('accounts.enable') . ' __N__?', 'body' => __('accounts.enable_confirm'), 'label' => __('accounts.enable'), 'method' => 'PUT', 'tone' => 'primary', 'data' => ['disabled' => '0']],
        'delete' => ['title' => __('accounts.delete_title'), 'body' => __('accounts.delete_confirm'), 'label' => __('accounts.delete'), 'method' => 'DELETE', 'tone' => 'destructive'],
    ];
    $menu = [
        ['key' => 'edit', 'label' => __('accounts.edit'), 'type' => 'link', 'modal' => true, 'accept' => '/admin/users', 'url' => route('admin.users.edit', '__P__')],
        ['key' => 'password', 'label' => __('accounts.reset_password'), 'type' => 'link', 'modal' => true, 'accept' => '/admin/users', 'url' => route('admin.users.password.edit', '__P__')],
        ['key' => 'disable', 'label' => __('accounts.disable'), 'type' => 'confirm', 'danger' => true, 'url' => route('admin.users.status.update', '__P__')],
        ['key' => 'enable', 'label' => __('accounts.enable'), 'type' => 'confirm', 'url' => route('admin.users.status.update', '__P__')],
        ['key' => 'delete', 'label' => __('accounts.delete'), 'type' => 'confirm', 'danger' => true, 'reason' => __('accounts.delete_owns_events'), 'url' => route('admin.users.destroy', '__P__')],
    ];
    $active = $users->whereNull('disabled_at')->count();
    $tz = config('app.display_timezone');
@endphp
<x-layouts.host :title="__('accounts.host_accounts')"
    :breadcrumbs="[[__('host.nav.admin'), null], [__('accounts.host_accounts'), null]]">
    <x-slot:actions>
        <x-button :href="route('admin.users.create')" up-layer="new modal" up-history="false" up-accept-location="/admin/users" up-size="medium"><x-icon name="plus" />{{ __('accounts.add_host') }}</x-button>
    </x-slot:actions>

    @error('account')
        <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
    @enderror

    {{-- U4: search, filter, sort and pages in the browser; the view state stays in localStorage. --}}
    <div x-data="tableView({ key: 'accounts', sort: 'name' })" x-init="$store.confirm.texts = @js($confirmTexts); $store.rowMenu.spec = @js($menu)" class="flex flex-col gap-4">
        <x-table.toolbar :search="__('accounts.search')">
            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('accounts.status') }}">
                @foreach (['all' => [__('accounts.all'), $users->count()], 'active' => [__('accounts.status_active'), $active], 'disabled' => [__('accounts.status_disabled'), $users->count() - $active]] as $key => [$label, $count])
                    <button type="button" @click="filter = @js($key)" :aria-pressed="(filter === @js($key)).toString()"
                        class="inline-flex h-11 items-center rounded-control px-3.5 text-sm font-semibold"
                        :class="filter === @js($key) ? 'bg-inverse text-on-inverse' : 'border border-line-strong bg-surface text-ink hover:bg-subtle'">{{ $label }} · {{ $count }}</button>
                @endforeach
            </div>
        </x-table.toolbar>

        <div class="rounded-card bg-surface max-lg:overflow-x-auto">
            <table class="w-full min-w-[820px] text-left text-[15px]" @click="onRowClick($event)">
                <thead class="z-10 border-b border-line-soft bg-surface text-xs font-bold tracking-[0.06em] text-muted uppercase lg:sticky lg:top-16">
                    <tr>
                        <x-table.th sort="name">{{ __('accounts.name') }}</x-table.th>
                        <x-table.th sort="username">{{ __('accounts.username') }}</x-table.th>
                        <x-table.th sort="email">{{ __('accounts.email') }}</x-table.th>
                        <x-table.th sort="role">{{ __('accounts.role') }}</x-table.th>
                        <x-table.th sort="status">{{ __('accounts.status') }}</x-table.th>
                        <x-table.th sort="added">{{ __('accounts.added_column') }}</x-table.th>
                        <x-table.th actions>{{ __('accounts.actions') }}</x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $account)
                        @php
                            $self = $account->is(auth()->user());
                            // C-7: you can edit your account and reset your password, never disable or delete it.
                            $flags = array_filter([
                                'data-edit' => '1',
                                'data-password' => '1',
                                'data-disable' => ! $self && ! $account->disabled_at ? '1' : null,
                                'data-enable' => ! $self && $account->disabled_at ? '1' : null,
                                'data-delete' => $self ? null : ($account->all_owned_events_count > 0 ? 'off' : '1'),
                            ]);
                        @endphp
                        <tr data-row data-search="{{ mb_strtolower($account->name . ' ' . $account->username . ' ' . $account->email) }}" data-filter="{{ $account->disabled_at ? 'disabled' : 'active' }}"
                            data-sort-name="{{ $account->name }}" data-sort-username="{{ $account->username }}" data-sort-email="{{ $account->email }}" data-sort-role="{{ $account->is_super_admin ? 1 : 2 }}" data-sort-status="{{ $account->disabled_at ? 2 : 1 }}" data-sort-added="{{ $account->created_at?->getTimestamp() }}"
                            class="border-b border-line-soft last:border-0">
                            <td class="py-3.5 pr-4 pl-6">
                                <span class="flex items-center gap-2"><span class="font-bold">{{ $account->name }}</span>
                                @if ($self)
                                    <span class="rounded-full bg-subtle px-2 py-0.5 text-xs font-semibold text-on-subtle">{{ __('accounts.you') }}</span>
                                @endif
                                </span>
                            </td>
                            <td class="px-4 py-3.5 font-mono text-sm">{{ $account->username }}</td>
                            <td class="px-4 py-3.5 text-muted">{{ $account->email }}</td>
                            <td class="px-4 py-3.5">{{ $account->is_super_admin ? __('accounts.role_super_admin') : __('accounts.role_host') }}</td>
                            <td class="px-4 py-3.5">
                                @if ($account->disabled_at)
                                    <x-chip>{{ __('accounts.status_disabled') }}</x-chip>
                                @else
                                    <x-chip tone="success" dot>{{ __('accounts.status_active') }}</x-chip>
                                @endif
                            </td>
                            {{-- U6: when the account was made, in WIB (T10). --}}
                            <td class="px-4 py-3.5 text-sm text-muted tabular-nums">{{ $account->created_at?->timezone($tz)->format('j M Y') }}</td>
                            {{-- U5: more than two actions, so they sit in the row menu. --}}
                            <td class="px-4 py-2 text-center">
                                <button type="button" class="row-action" data-menu data-id="{{ $account->id }}" data-name="{{ $account->name }}" @foreach ($flags as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach
                                    aria-haspopup="menu" aria-label="{{ __('accounts.actions_for', ['name' => $account->name]) }}" title="{{ __('accounts.actions') }}"><x-icon name="more" /></button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p x-show="shown === 0" x-cloak class="px-6 py-8 text-center text-muted">{{ __('accounts.no_match') }}</p>
            <x-table-pager />
        </div>
    </div>
</x-layouts.host>
