@php
    $locked = $event->names_locked_at !== null;
    $linkBase = url('/' . $event->slug . '/j') . '/';
    // U5: the confirmation texts are sent once; each row only adds its name and form address.
    $confirmTexts = [
        'release' => ['title' => __('people.release') . '?', 'body' => __('people.release_confirm', ['name' => '__N__']), 'label' => __('people.release'), 'method' => 'POST', 'tone' => 'primary'],
        'newLink' => ['title' => __('people.new_link') . '?', 'body' => __('people.new_link_confirm', ['name' => '__N__']), 'label' => __('people.new_link'), 'method' => 'POST', 'tone' => 'primary'],
        'delete' => ['title' => __('people.delete_title', ['name' => '__N__']), 'body' => __('people.delete_confirm'), 'label' => __('people.delete'), 'method' => 'DELETE', 'tone' => 'destructive'],
    ];
    $addFailed = old('form') === 'add-person' && $errors->has('name');
    // U5: the row menu offers an action only when its row carries an address for it.
    $menu = [
        ['key' => 'rename', 'label' => __('people.rename'), 'type' => 'link', 'url' => route('host.events.people.index', [$event, 'edit' => '__P__'])],
        ['key' => 'release', 'label' => __('people.release'), 'type' => 'confirm', 'url' => route('host.events.people.release-claim', [$event, '__P__'])],
        ['key' => 'newLink', 'label' => __('people.new_link'), 'type' => 'confirm', 'url' => route('host.events.people.regenerate-link', [$event, '__P__'])],
        ['key' => 'delete', 'label' => __('people.delete'), 'type' => 'confirm', 'danger' => true, 'url' => route('host.events.people.destroy', [$event, '__P__'])],
    ];
@endphp
<x-layouts.host :title="__('people.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, route('host.events.edit', $event)], [__('people.title'), null]]">
    @unless ($locked)
        {{-- U2: the page's one main action sits in the top bar. --}}
        <x-slot:actions>
            <x-button variant="secondary" :href="route('host.events.people.import.create', $event)">{{ __('people.import') }}</x-button>
            <x-confirm-dialog :title="__('people.add')" :trigger="__('people.add')" trigger-variant="primary" :open="$addFailed">
                <form method="POST" action="{{ route('host.events.people.store', $event) }}" class="flex flex-col gap-3" novalidate>
                    @csrf
                    <input type="hidden" name="form" value="add-person">
                    <x-field name="name" :label="__('people.name')" id="add-person-name" :hint="__('people.add_hint')" :submitted="old('form') === 'add-person'" maxlength="100" autocomplete="off" autofocus />
                    <div class="flex justify-end gap-2.5 pt-1.5">
                        <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                        <x-button type="submit">{{ __('people.add') }}</x-button>
                    </div>
                </form>
            </x-confirm-dialog>
        </x-slot:actions>
    @endunless

    <x-realtime :event="$event" :audience="\App\Enums\Audience::Host" />
    {{-- U12: join status, the lock banner and the list refresh when the event's version moves on. --}}
    <div id="names-live" x-data="liveRegion" class="flex flex-col gap-5">
    @if ($locked)
        {{-- B-3, B-6: the list is locked; say whether new claims still are, and switch them here. --}}
        <div role="status" class="flex flex-wrap items-center justify-between gap-3 rounded-card bg-warning px-4 py-3 text-sm font-semibold text-on-warning">
            <p>{{ __('people.locked_banner') }} {{ $event->join_locked_at ? __('people.claims_closed') : __('people.claims_open') }}</p>
            <form method="POST" action="{{ route('host.events.join-lock', $event) }}">
                @csrf
                <input type="hidden" name="locked" value="{{ $event->join_locked_at ? '0' : '1' }}">
                <x-button type="submit" variant="secondary">{{ $event->join_locked_at ? __('events.unlock') : __('events.lock') }}</x-button>
            </form>
        </div>
    @endif
    @error('names')
        <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
    @enderror

    @if ($people->isEmpty())
        <p class="rounded-card bg-surface px-6 py-10 text-center text-muted">{{ __('people.empty') }}</p>
    @else
        {{-- U4: search, filter and sort in the browser; the state stays in the URL. --}}
        <div x-data="tableView({ sort: 'name', copied: @js(__('people.copied', ['name' => '__N__'])), copyBase: @js($linkBase) })"
            x-init="$store.confirm.texts = @js($confirmTexts); $store.rowMenu.spec = @js($menu)" class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('people.status') }}">
                    @foreach (['all', 'joined', 'waiting'] as $key)
                        <button type="button" @click="filter = @js($key)" :aria-pressed="(filter === @js($key)).toString()"
                            class="inline-flex h-11 items-center rounded-control px-3.5 text-sm font-semibold"
                            :class="filter === @js($key) ? 'bg-inverse text-on-inverse' : 'border border-line-strong bg-surface text-ink hover:bg-subtle'">{{ __('people.' . $key) }} · {{ $counts[$key] }}</button>
                    @endforeach
                </div>
                <label class="relative flex w-full items-center sm:w-72">
                    <x-icon name="search" class="pointer-events-none absolute left-3 text-muted" />
                    <input type="search" x-model.debounce.150ms="q" aria-label="{{ __('people.search') }}" placeholder="{{ __('people.search') }}" autocomplete="off"
                        class="h-11 w-full rounded-control border border-line-strong bg-field pr-3 pl-10 text-[15px] text-ink">
                </label>
            </div>

            {{-- Sideways scrolling only on narrow screens: a scrolling box would pin the header to the box, not the page. --}}
            <div class="rounded-card bg-surface max-lg:overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-[15px]" @click="onRowClick($event)">
                    <thead class="z-10 border-b border-line-soft bg-surface lg:sticky lg:top-16 text-xs font-bold tracking-[0.06em] text-muted uppercase">
                        <tr>
                            <th scope="col" class="px-5 py-1" :aria-sort="ariaSort('name')">
                                <button type="button" class="inline-flex min-h-11 items-center gap-1.5 uppercase hover:text-ink" @click="sortBy('name')">{{ __('people.name') }} <x-icon name="sort" class="size-3.5" /></button>
                            </th>
                            <th scope="col" class="px-4 py-1" :aria-sort="ariaSort('joined')">
                                <button type="button" class="inline-flex min-h-11 items-center gap-1.5 uppercase hover:text-ink" @click="sortBy('joined')">{{ __('people.status') }} <x-icon name="sort" class="size-3.5" /></button>
                            </th>
                            <th scope="col" class="px-4 py-3">{{ __('people.link') }}</th>
                            <th scope="col" class="px-3 py-3"><span class="sr-only">{{ __('people.actions') }}</span></th>
                        </tr>
                    </thead>
                    {{-- A3, U5: cell styles live here once; each row carries only its data. --}}
                    <tbody class="[&_b]:font-bold [&_small]:block [&_small]:pt-1 [&_small]:text-xs [&_small]:text-muted [&_td]:px-4 [&_td]:py-2.5 [&_td:first-child]:pl-5 [&_td:last-child]:text-right [&>tr]:border-b [&>tr]:border-line-soft [&>tr:last-child]:border-0 [&_code]:font-mono [&_code]:text-sm [&_code]:text-muted [&_[data-copy]]:ml-2 [&_[data-copy]]:min-h-11 [&_[data-copy]]:text-sm [&_[data-copy]]:font-semibold [&_[data-copy]]:text-link [&_[data-copy]:hover]:underline [&_[data-menu]]:inline-flex [&_[data-menu]]:size-11 [&_[data-menu]]:items-center [&_[data-menu]]:justify-center [&_[data-menu]]:rounded-control [&_[data-menu]]:text-muted [&_[data-menu]:hover]:bg-subtle [&_[data-menu]:hover]:text-ink">
                        @foreach ($people as $person)
                            @php
                                // U5: flags only; the addresses come from the menu templates.
                                $actions = array_keys(array_filter([
                                    'data-rename' => ! $locked,
                                    'data-release' => $person->claimed_at !== null,
                                    'data-new-link' => true,
                                    'data-delete' => ! $locked,
                                ]));
                            @endphp
                            <tr data-row data-search="{{ mb_strtolower($person->name) }}" data-filter="{{ $person->claimed_at ? 'joined' : 'waiting' }}" data-sort-name="{{ $person->name }}" data-sort-joined="{{ $person->claimed_at?->getTimestamp() }}">
                                <td>
                                    @if (! $locked && $editing === $person->id)
                                        <form method="POST" action="{{ route('host.events.people.update', [$event, $person]) }}" class="flex items-end gap-2" novalidate>
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="form" value="rename-{{ $person->id }}">
                                            <x-field name="name" :label="__('people.rename')" :value="$person->name" :id="'rename-' . $person->id"
                                                :submitted="old('form') === 'rename-' . $person->id" maxlength="100" autofocus />
                                            <x-button type="submit" variant="secondary">{{ __('people.save') }}</x-button>
                                        </form>
                                    @else
                                        <b>{{ $person->name }}</b>
                                    @endif
                                </td>
                                {{-- U6: when the name joined, in WIB (T10). --}}
                                <td>@if ($person->claimed_at)<x-chip tone="success" dot>{{ __('people.status_joined') }}</x-chip><small>{{ __('people.joined_at', ['time' => $person->claimed_at->timezone(config('app.display_timezone'))->format('j M, H:i')]) }}</small>@else<x-chip>{{ __('people.status_waiting') }}</x-chip>@endif</td>
                                <td><code>/j/{{ $person->join_token }}</code><button type="button" data-copy="{{ $person->join_token }}" data-name="{{ $person->name }}">{{ __('people.copy') }}</button></td>
                                <td><button type="button" data-menu data-id="{{ $person->id }}" data-name="{{ $person->name }}" @foreach ($actions as $attribute) {{ $attribute }}="1" @endforeach aria-haspopup="menu" aria-label="{{ __('people.actions_for', ['name' => $person->name]) }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p x-show="shown === 0" x-cloak class="px-6 py-8 text-center text-muted">{{ __('people.no_match') }}</p>
            </div>
        </div>
    @endif
    </div>

    <section class="flex flex-wrap items-center justify-between gap-4 rounded-card bg-surface p-5" aria-labelledby="links-heading">
        <div class="flex max-w-2xl flex-col gap-1">
            <h2 id="links-heading" class="font-display text-xl font-semibold">{{ __('people.links_title') }}</h2>
            <p class="text-sm font-semibold text-on-warning">{{ __('people.links_warning') }}</p>
        </div>
        <x-button variant="secondary" :href="route('host.events.people.links', $event)">{{ __('people.export_links') }}</x-button>
    </section>
</x-layouts.host>
