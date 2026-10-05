@php
    $locked = $event->names_locked_at !== null;
    $linkBase = url('/' . $event->slug . '/j') . '/';
    // U5: the dialog texts and address templates are sent once; each row button only adds its
    // kind, id and name (data-ask).
    $confirmTexts = [
        'rename' => ['title' => __('people.rename_title'), 'label' => __('people.save'), 'method' => 'PUT', 'tone' => 'primary',
            'url' => route('host.events.people.update', [$event, '__P__']),
            'field' => ['name' => 'name', 'label' => __('people.name'), 'maxlength' => 100, 'form' => 'rename-__P__']],
        'release' => ['title' => __('people.release') . '?', 'body' => __('people.release_confirm', ['name' => '__N__']), 'label' => __('people.release'), 'method' => 'POST', 'tone' => 'primary',
            'url' => route('host.events.people.release-claim', [$event, '__P__'])],
        'newLink' => ['title' => __('people.new_link') . '?', 'body' => __('people.new_link_confirm', ['name' => '__N__']), 'label' => __('people.new_link'), 'method' => 'POST', 'tone' => 'primary',
            'url' => route('host.events.people.regenerate-link', [$event, '__P__'])],
        'delete' => ['title' => __('people.delete_title', ['name' => '__N__']), 'body' => __('people.delete_confirm'), 'label' => __('people.delete'), 'method' => 'DELETE', 'tone' => 'destructive',
            'url' => route('host.events.people.destroy', [$event, '__P__'])],
    ];
    // U5, U7: the labels and tooltips of the row icon buttons, also sent once.
    $labels = [
        'copy' => ['label' => __('people.copy_for', ['name' => '__N__']), 'title' => __('people.copy')],
        'menu' => ['label' => __('people.actions_for', ['name' => '__N__']), 'title' => __('people.actions')],
    ];
    // U5: Copy sits beside the link; the other actions are in the row menu of the Actions column.
    $menu = [
        ['key' => 'newLink', 'label' => __('people.new_link'), 'type' => 'confirm', 'url' => route('host.events.people.regenerate-link', [$event, '__P__'])],
        ['key' => 'release', 'label' => __('people.release'), 'type' => 'confirm', 'url' => route('host.events.people.release-claim', [$event, '__P__'])],
        ['key' => 'rename', 'label' => __('people.rename'), 'type' => 'confirm', 'url' => route('host.events.people.update', [$event, '__P__'])],
        ['key' => 'delete', 'label' => __('people.delete'), 'type' => 'confirm', 'danger' => true, 'url' => route('host.events.people.destroy', [$event, '__P__'])],
    ];
    $addFailed = old('form') === 'add-person' && $errors->has('name');
    // A refused rename reopens its dialog with what was typed and the error.
    $renaming = str_starts_with((string) old('form'), 'rename-') ? $people->firstWhere('id', substr((string) old('form'), 7)) : null;
    $renameRetry = $renaming ? [
        'row' => ['ask' => 'rename', 'id' => $renaming->id, 'name' => $renaming->name],
        'previous' => ['value' => old('name'), 'error' => $errors->first('name')],
    ] : null;
@endphp
<x-layouts.host :title="__('people.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, route('host.events.edit', $event)], [__('people.title'), null]]">
    {{-- U2, U21: the page actions head the content. --}}
    <x-slot:actions>
        @if ($people->isNotEmpty())
            <x-confirm-dialog :title="__('people.export_links') . '?'" :trigger="__('people.export_links')">
                <p class="text-[15px] font-semibold text-on-warning">{{ __('people.links_warning') }}</p>
                <div class="flex justify-end gap-2.5 pt-1.5">
                    <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                    <x-button :href="route('host.events.people.links', $event)" @click="$el.closest('dialog').close()">{{ __('people.export_links') }}</x-button>
                </div>
            </x-confirm-dialog>
        @endif
        @unless ($locked)
            <x-button variant="secondary" :href="route('host.events.people.import.create', $event)">{{ __('people.import') }}</x-button>
            <x-confirm-dialog :title="__('people.add')" :trigger="__('people.add')" trigger-icon="plus" trigger-variant="primary" :open="$addFailed">
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
        @endunless
    </x-slot:actions>

    <x-realtime :event="$event" :audience="\App\Enums\Audience::Host" />
    @if ($renameRetry)
        <script type="application/json" id="rename-retry">@json($renameRetry)</script>
    @endif
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
        <x-empty-state :title="__('people.empty_title')" :body="__('people.empty')">
            <x-button variant="secondary" :href="route('host.events.people.import.create', $event)">{{ __('people.import') }}</x-button>
        </x-empty-state>
    @else
        {{-- U4: search, filter, sort and pages in the browser; the view state stays in localStorage. --}}
        <div x-data="tableView({ key: 'names:{{ $event->id }}', sort: 'name', copied: @js(__('people.copied', ['name' => '__N__'])), copyBase: @js($linkBase), labels: @js($labels) })"
            x-init="$store.confirm.texts = @js($confirmTexts); $store.rowMenu.spec = @js($menu); const retry = document.getElementById('rename-retry'); if (retry) { const r = JSON.parse(retry.textContent); $store.confirm.askRow({ dataset: r.row }, r.previous) }"
            class="flex flex-col gap-4">
            <x-table.toolbar :search="__('people.search')">
                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('people.status') }}">
                    @foreach (['all', 'joined', 'waiting'] as $key)
                        <button type="button" @click="filter = @js($key)" :aria-pressed="(filter === @js($key)).toString()"
                            class="inline-flex h-11 items-center rounded-control px-3.5 text-sm font-semibold"
                            :class="filter === @js($key) ? 'bg-inverse text-on-inverse' : 'border border-line-strong bg-surface text-ink hover:bg-subtle'">{{ __('people.' . $key) }} · {{ $counts[$key] }}</button>
                    @endforeach
                </div>
            </x-table.toolbar>

            {{-- Sideways scrolling only on narrow screens: a scrolling box would pin the header to the box, not the page. --}}
            <div class="rounded-card bg-surface max-lg:overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-[15px]" @click="onRowClick($event)">
                    <thead class="z-10 border-b border-line-soft bg-surface text-xs font-bold tracking-[0.06em] text-muted uppercase lg:sticky lg:top-16">
                        <tr>
                            <x-table.th sort="name">{{ __('people.name') }}</x-table.th>
                            <x-table.th sort="joined">{{ __('people.status') }}</x-table.th>
                            <x-table.th sort="link">{{ __('people.link') }}</x-table.th>
                            <x-table.th actions>{{ __('people.actions') }}</x-table.th>
                        </tr>
                    </thead>
                    {{-- A3, U5: cell styles live here once; each row carries only its data. --}}
                    <tbody class="[&_b]:font-bold [&_small]:block [&_small]:pt-1 [&_small]:text-xs [&_small]:text-muted [&_td]:px-4 [&_td]:py-2.5 [&_td:first-child]:pl-6 [&_td:last-child]:text-center [&>tr]:border-b [&>tr]:border-line-soft [&>tr:last-child]:border-0 [&_code]:font-mono [&_code]:text-sm [&_code]:text-muted [&_td>span]:inline-flex [&_td>span]:items-center [&_td>span]:gap-1">
                        @foreach ($people as $person)
                            {{-- U5: the row carries its id and name once; its icon buttons only say what they do. --}}
                            {{-- One line per row (B-3: up to 500 rows). U6: when the name joined, in WIB (T10). B-6: a joined name is released right next to its status. --}}
<tr data-row data-id="{{ $person->id }}" data-name="{{ $person->name }}" data-search="{{ mb_strtolower($person->name) }} {{ $person->join_token }}" data-filter="{{ $person->claimed_at ? 'joined' : 'waiting' }}" data-sort-name="{{ $person->name }}" data-sort-joined="{{ $person->claimed_at?->getTimestamp() }}" data-sort-link="{{ $person->join_token }}"><td><b>{{ $person->name }}</b></td><td>@if ($person->claimed_at)<x-chip tone="success" dot>{{ __('people.status_joined') }}</x-chip><small>{{ __('people.joined_at', ['time' => $person->claimed_at->timezone(config('app.display_timezone'))->format('j M, H:i')]) }}</small>@else<x-chip>{{ __('people.status_waiting') }}</x-chip>@endif</td><td><span><code>/j/{{ $person->join_token }}</code><button type="button" class="row-action" data-copy="{{ $person->join_token }}"><i data-icon="copy"></i></button></span></td><td><button type="button" class="row-action" data-menu data-new-link="1"@if ($person->claimed_at) data-release="1"@endif @unless ($locked) data-rename="1" data-delete="1"@endunless aria-haspopup="menu"><i data-icon="more"></i></button></td></tr>
                        @endforeach
                    </tbody>
                </table>
                <p x-show="shown === 0" x-cloak class="px-6 py-8 text-center text-muted">{{ __('people.no_match') }}</p>
                <x-table-pager />
            </div>
        </div>
    @endif
    </div>
</x-layouts.host>
