@php
    $breadcrumbs = $showOwner
        ? [[__('host.nav.admin'), null], [__('events.trash'), null]]
        : [[__('host.dashboard.title'), route('host.dashboard')], [__('events.trash'), null]];
@endphp
<x-layouts.host :title="__('events.trash')" :breadcrumbs="$breadcrumbs">
    <p class="max-w-3xl text-sm text-muted">{{ __('events.trash_help') }}</p>

    <div class="flex flex-col gap-3">
        @forelse ($events as $event)
            @php $original = $event->originalSlug(); @endphp
            <section class="grid gap-4 rounded-card bg-surface p-5 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1.6fr)] lg:items-center">
                <div class="flex flex-col">
                    <h2 class="text-base font-bold">{{ $event->name }}</h2>
                    <span class="text-[13px] text-muted">{{ __('events.was_link', ['slug' => $original]) }}</span>
                </div>
                <div class="flex flex-col text-sm">
                    @if ($showOwner)
                        <span><span class="text-muted">{{ __('events.owner') }}:</span> {{ $event->owner?->name }}</span>
                    @endif
                    <span class="text-muted">
                        {{ __('events.deleted') }}
                        {{ __('events.deleted_by', ['name' => $event->deletedBy?->name ?? '—', 'date' => $event->deleted_at?->timezone(config('app.display_timezone'))->format('j M Y')]) }}
                    </span>
                </div>
                {{-- U5: restoring is confirmed; the link it comes back under is chosen in the dialog. --}}
                <div class="flex lg:justify-end">
                    <x-confirm-dialog :title="__('events.restore_title', ['name' => $event->name])" :trigger="__('events.restore')"
                        :open="old('restoring') === $event->id && $errors->any()">
                        <p class="text-[15px] text-muted">{{ __('events.restore_confirm') }}</p>
                        <form method="POST" action="{{ route($restoreRoute, $event) }}" class="flex flex-col gap-3" novalidate>
                            @csrf
                            <input type="hidden" name="restoring" value="{{ $event->id }}">
                            <x-field name="slug" :label="__('events.restore_link')" :value="$original" :id="'restore-' . $event->id" :submitted="old('restoring') === $event->id" maxlength="60" />
                            <div class="flex justify-end gap-2.5 pt-1.5">
                                <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                                <x-button type="submit">{{ __('events.restore') }}</x-button>
                            </div>
                        </form>
                    </x-confirm-dialog>
                </div>
            </section>
        @empty
            <x-empty-state :title="__('events.trash_empty')" :body="__('events.trash_empty_hint')" />
        @endforelse
    </div>
</x-layouts.host>
