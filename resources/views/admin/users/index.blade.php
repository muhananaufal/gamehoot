@php
    // U4, U5: Disable asks first, and sends its choice in the form, never in the address.
    $confirmTexts = [
        'disable' => ['title' => __('accounts.disable_title', ['name' => '__N__']), 'body' => __('accounts.disable_confirm'), 'label' => __('accounts.disable'), 'method' => 'PUT', 'tone' => 'destructive',
            'url' => route('admin.users.status.update', '__P__'), 'data' => ['disabled' => '1']],
    ];
    $active = $users->whereNull('disabled_at')->count();
@endphp
<x-layouts.host :title="__('accounts.host_accounts')"
    :breadcrumbs="[[__('host.nav.admin'), null], [__('accounts.host_accounts'), null]]">
    <x-slot:actions>
        <x-button :href="route('admin.users.create')" up-layer="new modal" up-history="false" up-accept-location="/admin/users" up-size="medium"><x-icon name="plus" />{{ __('accounts.add_host') }}</x-button>
    </x-slot:actions>

    {{-- U4: search, filter, sort and pages in the browser; the view state stays in localStorage. --}}
    <div x-data="tableView({ key: 'accounts', sort: 'name' })" x-init="$store.confirm.texts = @js($confirmTexts)" class="flex flex-col gap-4">
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
            <table class="w-full min-w-[720px] text-left text-[15px]" @click="onRowClick($event)">
                <thead class="z-10 border-b border-line-soft bg-surface text-xs font-bold tracking-[0.06em] text-muted uppercase lg:sticky lg:top-16">
                    <tr>
                        <x-table.th sort="name">{{ __('accounts.name') }}</x-table.th>
                        <x-table.th sort="email">{{ __('accounts.email') }}</x-table.th>
                        <x-table.th sort="role">{{ __('accounts.role') }}</x-table.th>
                        <x-table.th sort="status">{{ __('accounts.status') }}</x-table.th>
                        <x-table.th sort="added">{{ __('accounts.added_column') }}</x-table.th>
                        <x-table.th><span class="sr-only">{{ __('accounts.actions') }}</span></x-table.th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $account)
                        <tr data-row data-id="{{ $account->id }}" data-name="{{ $account->name }}" data-search="{{ mb_strtolower($account->name . ' ' . $account->email) }}" data-filter="{{ $account->disabled_at ? 'disabled' : 'active' }}"
                            data-sort-name="{{ $account->name }}" data-sort-email="{{ $account->email }}" data-sort-role="{{ $account->is_super_admin ? 1 : 2 }}" data-sort-status="{{ $account->disabled_at ? 2 : 1 }}" data-sort-added="{{ $account->created_at?->getTimestamp() }}"
                            class="border-b border-line-soft last:border-0">
                            <td class="py-3.5 pr-4 pl-6">
                                <span class="flex items-center gap-2"><span class="font-bold">{{ $account->name }}</span>
                                @if ($account->is(auth()->user()))
                                    <span class="rounded-full bg-subtle px-2 py-0.5 text-xs font-semibold text-on-subtle">{{ __('accounts.you') }}</span>
                                @endif
                                </span>
                            </td>
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
                            <td class="px-4 py-3.5 text-sm text-muted tabular-nums">{{ $account->created_at?->timezone(config('app.display_timezone'))->format('j M Y') }}</td>
                            {{-- U5: two actions at most, so both are icon buttons in the row. --}}
                            <td class="px-4 py-2">
                                <div class="flex items-center justify-end gap-1">
                                    @unless ($account->is(auth()->user()))
                                    @if ($account->disabled_at)
                                        <form method="POST" action="{{ route('admin.users.status.update', $account) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="disabled" value="0">
                                            <button type="submit" class="row-action" aria-label="{{ __('accounts.enable_for', ['name' => $account->name]) }}" title="{{ __('accounts.enable') }}"><i data-icon="check"></i></button>
                                        </form>
                                    @else
                                        {{-- U5: the password is reset in a dialog; it reopens with the error after a rejected one. --}}
                                        <x-confirm-dialog :title="__('accounts.reset_password_for', ['name' => $account->name])" :trigger="__('accounts.reset_password_for', ['name' => $account->name])" trigger-icon="key" icon-only
                                            :open="old('resetting') === $account->id && $errors->any()">
                                            <form method="POST" action="{{ route('admin.users.password.update', $account) }}" class="flex flex-col gap-4 text-left font-normal" novalidate>
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="resetting" value="{{ $account->id }}">
                                                <p class="text-sm text-muted">{{ __('accounts.reset_password_help') }}</p>
                                                <x-field name="password" type="password" :label="__('accounts.new_password')" :hint="__('accounts.password_hint')" :id="'reset-' . $account->id . '-password'"
                                                    :submitted="old('resetting') === $account->id" autocomplete="new-password" required />
                                                <x-field name="password_confirmation" type="password" :label="__('accounts.password_confirmation')" :id="'reset-' . $account->id . '-confirmation'"
                                                    :submitted="old('resetting') === $account->id" autocomplete="new-password" required />
                                                <div class="flex justify-end gap-2.5 pt-1.5">
                                                    <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                                                    <x-button type="submit">{{ __('accounts.save_password') }}</x-button>
                                                </div>
                                            </form>
                                        </x-confirm-dialog>
                                        {{-- U5: disabling signs the host out, so it asks first. --}}
                                        <button type="button" class="row-action" data-ask="disable" aria-label="{{ __('accounts.disable_for', ['name' => $account->name]) }}" title="{{ __('accounts.disable') }}"><i data-icon="block"></i></button>
                                    @endif
                                    @endunless
                                </div>
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
