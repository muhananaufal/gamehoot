@props([
    'title',
    // list of [label, url|null]; the last item is the current page.
    'breadcrumbs' => [],
    // F20, U1: the event being worked on opens its pages under its name in the sidebar.
    'event' => null,
    // U2: false for the pages that are a dialog's content and carry their own title (h2).
    'heading' => true,
])
@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $initials = collect(explode(' ', $user->name))->filter()->take(2)->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $recent = \App\Http\Views\RecentEvents::for($user, $event);
    $eventPages = [
        ['host.events.live', 'broadcast', __('realtime.live.title'), 'host.events.live'],
        ['host.events.games.index', 'layers', __('games.title'), 'host.events.games.*'],
        ['host.events.people.index', 'user', __('host.nav.names'), 'host.events.people.*'],
        ['host.events.results', 'calendar', __('results.title'), 'host.events.results'],
        ['host.events.logs', 'layers', __('logs.title'), 'host.events.logs'],
        ['host.events.edit', 'settings', __('host.nav.settings'), 'host.events.edit'],
    ];
@endphp
<x-layouts.base :title="$title">
    {{-- U18: links and forms inside data-host-shell move without reloading (resources/js/navigation.js). --}}
    <div x-data="hostShell" data-host-shell class="flex min-h-dvh" @keydown.escape.window="menuOpen = false">
        {{-- F20: one sidebar; on phones it becomes a drawer. --}}
        <div x-show="menuOpen" x-cloak class="fixed inset-0 z-30 bg-nav/70 lg:hidden" @click="menuOpen = false"></div>
        {{-- U1: the sidebar scrolls on its own, so theme and sign out stay in view on long pages. --}}
        <aside id="host-sidebar" :data-collapsed="collapsed"
            class="group fixed inset-y-0 left-0 z-40 flex h-dvh w-64 -translate-x-full flex-col bg-nav text-body text-nav-ink drawer lg:sticky lg:top-0 lg:h-dvh lg:shrink-0 lg:translate-x-0 lg:data-[collapsed=true]:w-[4.75rem]"
            :class="{ 'translate-x-0': menuOpen }">
            <div class="flex shrink-0 items-center justify-between px-5.5 pt-4 pb-4 lg:group-data-[collapsed=true]:justify-center lg:group-data-[collapsed=true]:px-0">
                <span class="lg:group-data-[collapsed=true]:hidden"><x-logo :size="32" class="text-title-s" /></span>
                <span class="hidden lg:group-data-[collapsed=true]:block"><x-logo :size="32" :wordmark="false" /></span>
                <button type="button" class="flex size-11 items-center justify-center rounded-control text-nav-icon lg:hidden"
                    @click="menuOpen = false" aria-label="{{ __('host.nav.close_menu') }}">
                    <x-icon name="close" />
                </button>
            </div>

            <nav id="host-nav" up-hungry class="flex min-h-0 grow flex-col gap-0.5 overflow-y-auto px-3.5 pb-3" aria-label="{{ __('host.nav.main') }}">
                <span class="px-3 pt-3.5 pb-1.5 text-[11px] font-bold tracking-[0.08em] text-nav-label uppercase lg:group-data-[collapsed=true]:sr-only">{{ __('host.nav.workspace') }}</span>
                <x-host-nav-link :href="route('host.dashboard')" icon="calendar" :active="request()->routeIs('host.dashboard')">
                    {{ __('host.nav.events') }}
                </x-host-nav-link>
                <x-host-nav-link :href="route('host.packs.index')" icon="layers" :active="request()->routeIs('host.packs.*')">
                    {{ __('host.nav.question_packs') }}
                </x-host-nav-link>
                <x-host-nav-link :href="route('host.trash.index')" icon="trash" :active="request()->routeIs('host.trash.*')">
                    {{ __('host.nav.deleted_events') }}
                </x-host-nav-link>

                @if ($recent->isNotEmpty())
                    <span class="px-3 pt-3.5 pb-1.5 text-[11px] font-bold tracking-[0.08em] text-nav-label uppercase lg:group-data-[collapsed=true]:sr-only">{{ __('host.nav.recent') }}</span>
                    @foreach ($recent as $item)
                        @php $current = $event?->is($item) ?? false; @endphp
                        {{-- U23: the name expands its pages in place instead of opening Live control, so
                             any page of a recent event is one click away. The event being worked on
                             starts expanded. --}}
                        <div x-data="{ open: @js($current) }" class="contents">
                        <button type="button" @click="open = ! open" :aria-expanded="open" aria-expanded="{{ $current ? 'true' : 'false' }}" title="{{ $item->name }}"
                            aria-controls="recent-{{ $item->id }}"
                            @class([
                                'flex h-11 w-full shrink-0 items-center gap-3 rounded-control px-3 text-left lg:group-data-[collapsed=true]:justify-center lg:group-data-[collapsed=true]:px-0',
                                'font-bold text-nav-ink' => $current,
                                'font-medium text-nav-muted hover:bg-nav-active hover:text-nav-ink' => ! $current,
                            ])>
                            <span @class(['flex size-[18px] shrink-0 items-center justify-center'])>
                                <span @class(['size-2 rounded-full', 'bg-success-dot' => $item->status === \App\Enums\EventStatus::Open, 'bg-warning-dot' => $item->status === \App\Enums\EventStatus::Finished, 'bg-nav-icon' => $item->status === \App\Enums\EventStatus::Draft])
                                    aria-hidden="true"></span>
                            </span>
                            <span class="grow truncate lg:group-data-[collapsed=true]:sr-only">{{ $item->name }}</span>
                            <span class="sr-only">{{ __('host.event_status.' . $item->status->value) }}</span>
                            <x-icon name="chevron-down" class="size-4 shrink-0 text-nav-icon transition-transform lg:group-data-[collapsed=true]:hidden" x-bind:class="open && 'rotate-180'" />
                        </button>
                        <div id="recent-{{ $item->id }}" x-show="open" @unless ($current) x-cloak @endunless class="flex flex-col gap-0.5 pl-3 lg:group-data-[collapsed=true]:pl-0">
                            @foreach ($eventPages as [$route, $icon, $label, $pattern])
                                <x-host-nav-link :href="route($route, $item)" :icon="$icon" :active="$current && request()->routeIs($pattern)">{{ $label }}</x-host-nav-link>
                            @endforeach
                        </div>
                        </div>
                    @endforeach
                @endif

                @can('viewAny', \App\Models\User::class)
                    <span class="px-3 pt-3.5 pb-1.5 text-[11px] font-bold tracking-[0.08em] text-nav-label uppercase lg:group-data-[collapsed=true]:sr-only">{{ __('host.nav.admin') }}</span>
                    <x-host-nav-link :href="route('admin.users.index')" icon="user" :active="request()->routeIs('admin.users.*')">
                        {{ __('host.nav.host_accounts') }}
                    </x-host-nav-link>
                    <x-host-nav-link :href="route('admin.trash.index')" icon="trash" :active="request()->routeIs('admin.trash.*')">
                        {{ __('host.nav.all_deleted_events') }}
                    </x-host-nav-link>
                @endcan
            </nav>

            <div class="flex shrink-0 items-center gap-2.5 border-t border-nav-active px-5.5 py-3 lg:group-data-[collapsed=true]:flex-col lg:group-data-[collapsed=true]:px-2">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-pink font-display text-sm font-bold text-brand-pupil" title="{{ $user->name }}" aria-hidden="true">{{ $initials }}</span>
                <div class="flex min-w-0 grow flex-col lg:group-data-[collapsed=true]:sr-only">
                    <span class="truncate text-sm font-bold">{{ $user->name }}</span>
                    <span class="truncate text-xs whitespace-nowrap text-nav-icon">{{ $user->is_super_admin ? __('accounts.role_super_admin') : __('accounts.role_host') }}</span>
                </div>
                <x-theme-toggle class="text-nav-icon hover:bg-nav-active" />
                <x-motion-toggle class="text-nav-icon hover:bg-nav-active" />
                <form method="POST" action="{{ route('logout') }}" up-submit="false">
                    @csrf
                    <button type="submit" aria-label="{{ __('accounts.sign_out') }}" title="{{ __('accounts.sign_out') }}"
                        class="flex size-11 items-center justify-center rounded-control text-nav-icon hover:bg-nav-active">
                        <x-icon name="logout" />
                    </button>
                </form>
            </div>
        </aside>
        {{-- U1: apply a remembered narrow sidebar before the first paint, so it does not flash wide. --}}
        <script @if ($nonce = \Illuminate\Support\Facades\Vite::cspNonce()) nonce="{{ $nonce }}" @endif>
            try { if (localStorage.getItem('pentahoot-sidebar') === 'collapsed') document.getElementById('host-sidebar').dataset.collapsed = 'true'; } catch (e) {}
        </script>

        {{-- U18: the part a navigation replaces; the sidebar keeps its scroll and only its links update (up-hungry). --}}
        <div id="host-content" up-main class="flex min-w-0 grow flex-col">
            <header class="sticky top-0 z-20 flex min-h-16 shrink-0 items-center gap-3 border-b border-line bg-bar px-4 lg:px-7">
                <button type="button" class="-ml-2 flex size-11 items-center justify-center rounded-control lg:hidden"
                    @click="menuOpen = true" aria-controls="host-sidebar" :aria-expanded="menuOpen.toString()"
                    aria-label="{{ __('host.nav.open_menu') }}">
                    <x-icon name="menu" />
                </button>
                <button type="button" class="-ml-3 hidden size-11 items-center justify-center rounded-control text-muted hover:bg-subtle hover:text-ink lg:flex"
                    @click="toggleCollapsed()" aria-controls="host-sidebar" :aria-expanded="(!collapsed).toString()"
                    :aria-label="collapsed ? @js(__('host.nav.expand')) : @js(__('host.nav.collapse'))"
                    aria-label="{{ __('host.nav.collapse') }}" :title="collapsed ? @js(__('host.nav.expand')) : @js(__('host.nav.collapse'))">
                    <x-icon name="sidebar" />
                </button>
                <nav id="breadcrumbs" aria-label="{{ __('host.nav.breadcrumb') }}" class="flex min-w-0 grow items-center gap-2 text-body">
                    @foreach ($breadcrumbs as [$label, $url])
                        @if ($loop->last)
                            <span class="truncate font-bold" aria-current="page">{{ $label }}</span>
                        @else
                            <a href="{{ $url }}" class="inline-flex min-h-11 items-center truncate text-muted hover:text-ink">{{ $label }}</a>
                            <span class="text-line-strong" aria-hidden="true">/</span>
                        @endif
                    @endforeach
                </nav>
            </header>

            <main class="flex grow flex-col gap-5 px-4 py-6 lg:px-7">
                {{-- U2: the page title and its actions head the content; the top bar only shows where you are. --}}
                @if ($heading)
                    {{-- U14: an id, so a page can swap its title and actions alone. --}}
                    <div id="content-head" class="flex flex-wrap items-center justify-between gap-3">
                        <h1 class="font-display text-[26px] font-bold">{{ last($breadcrumbs)[0] }}</h1>
                        @isset($actions)
                            <div class="flex flex-wrap items-center gap-2.5">{{ $actions }}</div>
                        @endisset
                    </div>
                @endif
                {{ $slot }}
            </main>
            <x-toasts />
        </div>
        {{-- U5, U18: inside the shell, so their forms and links move without reloading too. --}}
        <x-confirm-host />
        <x-row-menu-host />
    </div>
</x-layouts.base>
