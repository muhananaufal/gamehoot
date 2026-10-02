@php
    use Illuminate\Support\Js;

    $confirmTexts = [
        'disable' => ['title' => __('accounts.disable_title', ['name' => '__N__']), 'body' => __('accounts.disable_confirm'), 'label' => __('accounts.disable'), 'method' => 'PUT', 'tone' => 'destructive'],
    ];
    $active = $users->whereNull('disabled_at')->count();
@endphp
<x-layouts.host :title="__('accounts.host_accounts')"
    :breadcrumbs="[[__('host.nav.admin'), null], [__('accounts.host_accounts'), null]]">
    <x-slot:actions>
        <x-button :href="route('admin.users.create')"><x-icon name="plus" />{{ __('accounts.add_host') }}</x-button>
    </x-slot:actions>

    {{-- U4: search, filter and sort in the browser; the state stays in the URL. --}}
    <div x-data="tableView({ sort: 'name' })" x-init="$store.confirm.texts = @js($confirmTexts)" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('accounts.status') }}">
                @foreach (['all' => [__('accounts.all'), $users->count()], 'active' => [__('accounts.status_active'), $active], 'disabled' => [__('accounts.status_disabled'), $users->count() - $active]] as $key => [$label, $count])
                    <button type="button" @click="filter = @js($key)" :aria-pressed="(filter === @js($key)).toString()"
                        class="inline-flex h-11 items-center rounded-control px-3.5 text-sm font-semibold"
                        :class="filter === @js($key) ? 'bg-inverse text-on-inverse' : 'border border-line-strong bg-surface text-ink hover:bg-subtle'">{{ $label }} · {{ $count }}</button>
                @endforeach
            </div>
            <label class="relative flex w-full items-center sm:w-72">
                <x-icon name="search" class="pointer-events-none absolute left-3 text-muted" />
                <input type="search" x-model.debounce.150ms="q" aria-label="{{ __('accounts.search') }}" placeholder="{{ __('accounts.search') }}" autocomplete="off"
                    class="h-11 w-full rounded-control border border-line-strong bg-field pr-3 pl-10 text-[15px] text-ink">
            </label>
        </div>

        <div class="rounded-card bg-surface max-lg:overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-[15px]">
                <thead class="z-10 border-b border-line-soft bg-surface text-xs font-bold tracking-[0.06em] text-muted uppercase lg:sticky lg:top-16">
                    <tr>
                        <th scope="col" class="px-5 py-1" :aria-sort="ariaSort('name')">
                            <button type="button" class="inline-flex min-h-11 items-center gap-1.5 uppercase hover:text-ink" @click="sortBy('name')">{{ __('accounts.name') }} <x-icon name="sort" class="size-3.5" /></button>
                        </th>
                        <th scope="col" class="px-4 py-3">{{ __('accounts.email') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('accounts.role') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('accounts.status') }}</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('accounts.status') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $account)
                        <tr data-row data-search="{{ mb_strtolower($account->name . ' ' . $account->email) }}" data-filter="{{ $account->disabled_at ? 'disabled' : 'active' }}" data-sort-name="{{ $account->name }}"
                            class="border-b border-line-soft last:border-0">
                            <td class="px-5 py-3.5">
                                <span class="block font-bold">{{ $account->name }}</span>
                                {{-- U6: when the account was made, in WIB (T10). --}}
                                <span class="text-[13px] text-muted">{{ __('accounts.added', ['date' => $account->created_at?->timezone(config('app.display_timezone'))->format('j M Y')]) }}</span>
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
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end gap-4 text-sm font-semibold">
                                    @if ($account->is(auth()->user()))
                                        <span class="text-muted">{{ __('accounts.you') }}</span>
                                    @elseif ($account->disabled_at)
                                        <form method="POST" action="{{ route('admin.users.status.update', $account) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="disabled" value="0">
                                            <x-button type="submit" variant="link">{{ __('accounts.enable') }}</x-button>
                                        </form>
                                    @else
                                        <x-button variant="link" :href="route('admin.users.password.edit', $account)">{{ __('accounts.reset_password') }}</x-button>
                                        {{-- U5: disabling signs the host out, so it asks first. --}}
                                        <x-button type="button" variant="link-destructive"
                                            @click="$store.confirm.askFor('disable', {{ Js::from($account->name) }}, {{ Js::from(route('admin.users.status.update', [$account, 'disabled' => 1])) }})">{{ __('accounts.disable') }}</x-button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p x-show="shown === 0" x-cloak class="px-6 py-8 text-center text-muted">{{ __('accounts.no_match') }}</p>
        </div>
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
