@php
    $breadcrumbs = $showOwner
        ? [[__('host.nav.admin'), null], [__('events.trash'), null]]
        : [[__('host.dashboard.title'), route('host.dashboard')], [__('events.trash'), null]];
    $tz = config('app.display_timezone');
    // U23: who deleted the listed events, for the Deleted by filter.
    $deleters = $events->map(fn ($event) => $event->deletedBy)->filter()->unique('id')->sortBy('name')->values();
@endphp
<x-layouts.host :title="__('events.trash')" :breadcrumbs="$breadcrumbs">
    <p class="text-sm text-muted">{{ __('events.trash_help') }}</p>

    @if ($events->isEmpty())
        <x-empty-state :title="__('events.trash_empty')" :body="__('events.trash_empty_hint')" />
    @else
        {{-- U4: the shared table look; search, sort and pages in the browser. --}}
        <div x-data="tableView({ key: 'trash', sort: 'deleted', dir: 'desc' })" class="flex flex-col gap-4">
            {{-- U23: the date the event was deleted beside the search; Deleted by beside Rows per page. --}}
            <x-table.toolbar :search="__($showOwner ? 'events.trash_search_owner' : 'events.trash_search')" :today="now()->timezone($tz)->format('Y-m-d')" :date-label="__('events.deleted_date_filter')">
                <x-select :options="[
                    ['value' => 'all', 'label' => __('events.deleted_by_anyone')],
                    ...$deleters->map(fn ($user): array => ['value' => $user->id, 'label' => $user->name])->all(),
                ]" :label="__('events.deleted_by_column')" x-model="who" />
            </x-table.toolbar>

            <div class="rounded-card bg-surface max-lg:overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-[15px]">
                    <thead class="z-10 border-b border-line-soft bg-surface text-xs font-bold tracking-[0.06em] text-muted uppercase lg:sticky lg:top-16">
                        <tr>
                            <x-table.th sort="name">{{ __('host.dashboard.event') }}</x-table.th>
                            @if ($showOwner)
                                <x-table.th sort="owner">{{ __('events.owner') }}</x-table.th>
                            @endif
                            <x-table.th sort="by">{{ __('events.deleted_by_column') }}</x-table.th>
                            <x-table.th sort="deleted">{{ __('events.deleted') }}</x-table.th>
                            <x-table.th actions>{{ __('ui.actions') }}</x-table.th>
                        </tr>
                    </thead>
                    <tbody class="[&_td]:px-4 [&_td]:py-2.5 [&_td:first-child]:pl-6 [&>tr]:border-b [&>tr]:border-line-soft [&>tr:last-child]:border-0">
                        @foreach ($events as $event)
                            @php $original = $event->originalSlug(); @endphp
                            <tr data-row data-search="{{ mb_strtolower($event->name . ' ' . $original . ' ' . $event->owner?->name) }}"
                                data-sort-name="{{ $event->name }}" data-sort-owner="{{ $event->owner?->name }}" data-sort-by="{{ $event->deletedBy?->name }}" data-sort-deleted="{{ $event->deleted_at?->getTimestamp() }}"
                                data-who="{{ $event->deleted_by }}" data-date="{{ $event->deleted_at?->timezone($tz)->format('Y-m-d') }}">
                                <td>
                                    <b class="block">{{ $event->name }}</b>
                                    <span class="text-[13px] text-muted">{{ __('events.was_link', ['slug' => $original]) }}</span>
                                </td>
                                @if ($showOwner)
                                    <td>{{ $event->owner?->name }}</td>
                                @endif
                                <td>{{ $event->deletedBy?->name ?? '—' }}</td>
                                <td class="text-sm text-muted tabular-nums">{{ $event->deleted_at?->timezone($tz)->format('j M Y') }}</td>
                                {{-- U5: restoring is confirmed; the link it comes back under is chosen in the dialog. --}}
                                <td class="text-center">
                                    <x-confirm-dialog :title="__('events.restore_title', ['name' => $event->name])" :trigger="__('events.restore_named', ['name' => $event->name])" trigger-icon="refresh" icon-only
                                        :open="old('restoring') === $event->id && $errors->any()">
                                        <p class="text-left text-[15px] text-muted">{{ __('events.restore_confirm') }}</p>
                                        <form method="POST" action="{{ route($restoreRoute, $event) }}" class="flex flex-col gap-3 text-left" novalidate>
                                            @csrf
                                            <input type="hidden" name="restoring" value="{{ $event->id }}">
                                            <x-field name="slug" :label="__('events.restore_link')" :value="$original" :id="'restore-' . $event->id" :submitted="old('restoring') === $event->id"
                                                :prefix="parse_url(config('app.url'), PHP_URL_HOST) . '/'" required maxlength="60" pattern="[a-z0-9]+(-[a-z0-9]+)*" minlength="3" :data-pattern-message="__('events.slug_format')" autocapitalize="none" spellcheck="false" />
                                            <div class="flex justify-end gap-2.5 pt-1.5">
                                                <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                                                <x-button type="submit">{{ __('events.restore') }}</x-button>
                                            </div>
                                        </form>
                                    </x-confirm-dialog>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p x-show="shown === 0" x-cloak class="px-6 py-8 text-center text-muted">{{ __('host.dashboard.empty_filtered') }}</p>
                <x-table-pager />
            </div>
        </div>
    @endif
</x-layouts.host>
