@props([
    'title',
    // list of [label, url|null]; the last item is the current page.
    'breadcrumbs' => [],
    // F20: the event menu appears while working inside one event.
    'event' => null,
])
@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
@endphp
<x-layouts.base :title="$title">
    <div x-data="{ menuOpen: false }" class="flex min-h-dvh" @keydown.escape.window="menuOpen = false">
        {{-- F20: one sidebar; on phones it becomes a drawer. --}}
        <div x-show="menuOpen" x-cloak class="fixed inset-0 z-30 bg-nav/70 lg:hidden" @click="menuOpen = false"></div>
        <aside id="host-sidebar"
            class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-nav px-3.5 py-4 text-[15px] text-nav-ink transition-transform lg:static lg:translate-x-0"
            :class="{ 'translate-x-0': menuOpen }">
            <div class="flex items-center justify-between px-2 pb-4">
                <x-logo :size="32" class="text-[22px]" />
                <button type="button" class="flex size-11 items-center justify-center rounded-control text-nav-icon lg:hidden"
                    @click="menuOpen = false" aria-label="{{ __('host.nav.close_menu') }}">
                    <x-icon name="close" />
                </button>
            </div>

            <nav class="flex flex-col gap-0.5" aria-label="{{ __('host.nav.main') }}">
                <span class="px-3 pt-3.5 pb-1.5 text-[11px] font-bold tracking-[0.08em] text-nav-label uppercase">{{ __('host.nav.workspace') }}</span>
                <x-host-nav-link :href="route('host.dashboard')" icon="calendar" :active="request()->routeIs('host.dashboard')">
                    {{ __('host.nav.events') }}
                </x-host-nav-link>
                <x-host-nav-link :href="route('host.packs.index')" icon="layers" :active="request()->routeIs('host.packs.*')">
                    {{ __('host.nav.question_packs') }}
                </x-host-nav-link>
                <x-host-nav-link :href="route('host.trash.index')" icon="trash" :active="request()->routeIs('host.trash.*')">
                    {{ __('host.nav.deleted_events') }}
                </x-host-nav-link>

                @if ($event)
                    <span class="truncate px-3 pt-3.5 pb-1.5 text-[11px] font-bold tracking-[0.08em] text-nav-label uppercase">{{ $event->name }}</span>
                    <x-host-nav-link :href="route('host.events.people.index', $event)" icon="user" :active="request()->routeIs('host.events.people.*')">
                        {{ __('host.nav.names') }}
                    </x-host-nav-link>
                    <x-host-nav-link :href="route('host.events.edit', $event)" icon="settings" :active="request()->routeIs('host.events.edit')">
                        {{ __('host.nav.settings') }}
                    </x-host-nav-link>
                @endif

                @can('viewAny', \App\Models\User::class)
                    <span class="px-3 pt-3.5 pb-1.5 text-[11px] font-bold tracking-[0.08em] text-nav-label uppercase">{{ __('host.nav.admin') }}</span>
                    <x-host-nav-link :href="route('admin.users.index')" icon="user" :active="request()->routeIs('admin.users.*')">
                        {{ __('host.nav.host_accounts') }}
                    </x-host-nav-link>
                    <x-host-nav-link :href="route('admin.trash.index')" icon="trash" :active="request()->routeIs('admin.trash.*')">
                        {{ __('host.nav.all_deleted_events') }}
                    </x-host-nav-link>
                @endcan
            </nav>

            <div class="mt-auto flex items-center gap-2.5 border-t border-nav-active px-2 pt-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-pink font-display text-sm font-bold text-brand-pupil" aria-hidden="true">{{ $initials }}</span>
                <div class="flex min-w-0 grow flex-col">
                    <span class="truncate text-sm font-bold">{{ $user->name }}</span>
                    <span class="truncate text-xs whitespace-nowrap text-nav-icon">{{ $user->is_super_admin ? __('accounts.role_super_admin') : __('accounts.role_host') }}</span>
                </div>
                <button type="button" x-data="themeToggle" @click="toggle()" aria-label="{{ __('host.nav.switch_theme') }}"
                    class="flex size-11 items-center justify-center rounded-control text-nav-icon hover:bg-nav-active">
                    <x-icon name="moon" />
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" aria-label="{{ __('accounts.sign_out') }}"
                        class="flex size-11 items-center justify-center rounded-control text-nav-icon hover:bg-nav-active">
                        <x-icon name="logout" />
                    </button>
                </form>
            </div>
        </aside>

        <div class="flex min-w-0 grow flex-col">
            <header class="flex min-h-16 shrink-0 items-center gap-3 border-b border-line bg-bar px-4 lg:px-7">
                <button type="button" class="-ml-2 flex size-11 items-center justify-center rounded-control lg:hidden"
                    @click="menuOpen = true" aria-controls="host-sidebar" :aria-expanded="menuOpen.toString()"
                    aria-label="{{ __('host.nav.open_menu') }}">
                    <x-icon name="menu" />
                </button>
                <nav aria-label="{{ __('host.nav.breadcrumb') }}" class="flex min-w-0 grow items-center gap-2 text-[15px]">
                    @foreach ($breadcrumbs as [$label, $url])
                        @if ($loop->last)
                            <span class="truncate font-bold" aria-current="page">{{ $label }}</span>
                        @else
                            <a href="{{ $url }}" class="truncate text-muted hover:text-ink">{{ $label }}</a>
                            <span class="text-line-strong" aria-hidden="true">/</span>
                        @endif
                    @endforeach
                </nav>
                @isset($actions)
                    <div class="flex items-center gap-2.5">{{ $actions }}</div>
                @endisset
            </header>

            <main class="flex grow flex-col gap-5 px-4 py-6 lg:px-7">
                <x-flash />
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
