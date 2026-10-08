@php
    use App\Enums\EventStatus;
@endphp
<x-layouts.host :title="__('events.settings') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('events.settings'), null]]">

    @error('event')
        <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
    @enderror

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)]">
        <form method="POST" action="{{ route('host.events.update', $event) }}" class="flex flex-col gap-5 rounded-card bg-surface p-6" novalidate>
            @csrf
            @method('PUT')
            {{-- U20: the event status sits beside General; the Status section keeps the locks. --}}
            <div class="flex items-center justify-between gap-3">
                <h2 class="font-display text-xl font-semibold">{{ __('events.general') }}</h2>
                <x-chip :tone="match ($event->status) { EventStatus::Open => 'success', EventStatus::Draft => 'neutral', EventStatus::Finished => 'warning' }" :dot="$event->status === EventStatus::Open">
                    {{ __('host.event_status.' . $event->status->value) }}
                </x-chip>
            </div>
            <x-field name="name" :placeholder="__('examples.event_name')" :label="__('events.name')" :value="$event->name" required maxlength="100" />
            @if ($event->status === \App\Enums\EventStatus::Open)
                {{-- F10: printed QR codes and open phones use the link of an open event. --}}
                <x-field name="slug" :placeholder="__('examples.slug')" :label="__('events.link')" :value="$event->slug" :prefix="parse_url(config('app.url'), PHP_URL_HOST) . '/'" :hint="__('events.link_locked_hint')" required readonly maxlength="60" autocapitalize="none" spellcheck="false" />
            @else
                <x-field name="slug" :placeholder="__('examples.slug')" :label="__('events.link')" :value="$event->slug" :prefix="parse_url(config('app.url'), PHP_URL_HOST) . '/'" :hint="__('events.link_hint')" required pattern="[a-z0-9]+(-[a-z0-9]+)*" minlength="3" :data-pattern-message="__('events.slug_format')" maxlength="60" autocapitalize="none" spellcheck="false" />
            @endif
            <h2 class="pt-2 font-display text-xl font-semibold">{{ __('events.screens') }}</h2>
            <x-theme-choice :value="$event->screen_theme->value" :themes="$themes" :hint="__('events.screen_theme_hint')" />
            <x-toggle name="show_on_devices" :label="__('events.show_on_devices')" :hint="__('events.show_on_devices_hint')" :checked="$event->show_on_devices" />
            <div class="flex justify-end pt-2">
                <x-button type="submit">{{ __('events.save') }}</x-button>
            </div>
        </form>

        <div class="flex flex-col gap-5">
            <section class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-labelledby="status-heading">
                <h2 id="status-heading" class="font-display text-xl font-semibold">{{ __('events.status') }}</h2>
                @if ($event->status === EventStatus::Draft)
                    <p class="text-sm text-muted">{{ __('events.open_event_help') }}</p>
                    {{-- U24: changes of state ask first. --}}
                    <div>
                        @include('host.partials.confirm-action', [
                            'trigger' => __('events.open_event'), 'title' => __('events.open_event') . '?', 'body' => __('events.open_confirm', ['name' => $event->name]),
                            'action' => route('host.events.open', $event), 'confirm' => __('events.open_event'),
                        ])
                    </div>
                @endif
                <div class="flex flex-col gap-2 border-t border-line-soft pt-4">
                    <p class="text-sm font-semibold">{{ __('events.join_lock') }}</p>
                    <p class="text-sm text-muted">{{ $event->join_locked_at ? __('events.join_locked_state') : __('events.join_lock_help') }}</p>
                    <div>
                        @include('host.partials.confirm-action', [
                            'trigger' => $event->join_locked_at ? __('events.unlock') : __('events.lock'), 'variant' => 'secondary',
                            'title' => ($event->join_locked_at ? __('events.unlock') : __('events.lock')) . '?',
                            'body' => $event->join_locked_at ? __('events.unlock_confirm') : __('events.lock_confirm'),
                            'action' => route('host.events.join-lock', $event), 'confirm' => $event->join_locked_at ? __('events.unlock') : __('events.lock'),
                            'fields' => ['locked' => $event->join_locked_at ? '0' : '1'],
                        ])
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-labelledby="cohosts-heading">
                <h2 id="cohosts-heading" class="font-display text-xl font-semibold">{{ __('events.hosts') }}</h2>
                <p class="text-sm text-muted">{{ __('events.cohosts_help') }}</p>
                {{-- U20: the owner first, with a badge. --}}
                <div class="flex items-center justify-between gap-3">
                    <span class="flex flex-col">
                        <span class="flex items-center gap-2"><span class="font-bold">{{ $owner->name }}</span><x-chip tone="accent">{{ __('events.owner') }}</x-chip></span>
                        <span class="text-sm text-muted">{{ $owner->email }}</span>
                    </span>
                </div>
                {{-- Then the co-hosts from the first added, each with when (U6, WIB). --}}
                @forelse ($cohosts as $cohost)
                    <div class="flex items-center justify-between gap-3 border-t border-line-soft pt-3">
                        <span class="flex flex-col">
                            <span class="font-bold">{{ $cohost->name }}</span>
                            <span class="text-sm text-muted">{{ $cohost->email }}</span>
                            @if ($cohost->pivot?->created_at)
                                <span class="text-xs text-muted">{{ __('events.added_at', ['date' => $cohost->pivot->created_at->timezone(config('app.display_timezone'))->format('j M Y')]) }}</span>
                            @endif
                        </span>
                        @if ($isOwner)
                            <form method="POST" action="{{ route('host.events.cohosts.destroy', [$event, $cohost]) }}">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="link-destructive">{{ __('events.remove') }}</x-button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('events.no_cohosts') }}</p>
                @endforelse
                @if ($isOwner)
                    <form method="POST" action="{{ route('host.events.cohosts.store', $event) }}" class="flex flex-col gap-3 border-t border-line-soft pt-4" novalidate>
                        @csrf
                        <x-field name="email" :placeholder="__('examples.username_or_email')" :label="__('events.cohost_email')" autocomplete="off" autocapitalize="none" spellcheck="false" required />
                        <x-button type="submit" variant="secondary" class="self-start">{{ __('events.add_cohost') }}</x-button>
                    </form>
                @endif
            </section>

            <section class="flex flex-col gap-5 rounded-card bg-surface p-6" aria-labelledby="owner-heading">
                <h2 id="owner-heading" class="font-display text-xl font-semibold">{{ __('events.owner_actions') }}</h2>

                @if ($event->status === EventStatus::Finished)
                    @can('reopen', $event)
                        <div class="flex flex-col gap-2">
                            <p class="text-sm text-muted">{{ __('events.reopen_help') }}</p>
                            <div>
                                @include('host.partials.confirm-action', [
                                    'trigger' => __('events.reopen'), 'variant' => 'secondary', 'title' => __('events.reopen') . '?', 'body' => __('events.reopen_confirm', ['name' => $event->name]),
                                    'action' => route('host.events.reopen', $event), 'confirm' => __('events.reopen'),
                                ])
                            </div>
                        </div>
                    @endcan
                @else
                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-semibold">{{ __('events.close') }}</p>
                        <p class="text-sm text-muted">{{ __('events.close_help') }}</p>
                        @include('host.events.partials.close-event', ['event' => $event, 'runningGame' => $runningGame])
                    </div>
                @endif

                @if ($isOwner)
                    {{-- U20: giving the event away is destructive: red, and confirmed in a dialog that
                         reopens with the error (unknown or disabled host). --}}
                    <div class="flex flex-col gap-2 border-t border-line-soft pt-4">
                        <p class="text-sm font-semibold">{{ __('events.transfer') }}</p>
                        <p class="text-sm text-muted">{{ __('events.transfer_help') }}</p>
                        <x-confirm-dialog :title="__('events.transfer') . '?'" :trigger="__('events.transfer')" trigger-variant="destructive" :open="$errors->has('new_owner_email')">
                            <p class="text-[15px] text-muted">{{ __('events.transfer_confirm', ['name' => $event->name]) }}</p>
                            <form method="POST" action="{{ route('host.events.transfer', $event) }}" class="flex flex-col gap-3" novalidate>
                                @csrf
                                <x-field name="new_owner_email" :placeholder="__('examples.username_or_email')" :label="__('events.new_owner_email')" autocomplete="off" autocapitalize="none" spellcheck="false" required />
                                <div class="flex justify-end gap-2.5 pt-1.5">
                                    <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                                    <x-button type="submit" variant="destructive">{{ __('events.transfer') }}</x-button>
                                </div>
                            </form>
                        </x-confirm-dialog>
                    </div>

                    <div class="flex flex-col gap-2 border-t border-line-soft pt-4">
                        <p class="text-sm font-semibold">{{ __('events.delete') }}</p>
                        <p class="text-sm text-muted">{{ __('events.delete_help') }}</p>
                        <x-confirm-dialog :title="__('events.delete') . '?'" :trigger="__('events.delete')" trigger-variant="destructive" :open="$errors->has('confirm_name')">
                            <p class="text-[15px] text-muted">{{ __('events.delete_help') }}</p>
                            <form x-data="{ typed: '' }" method="POST" action="{{ route('host.events.destroy', $event) }}" class="flex flex-col gap-3" novalidate>
                                @csrf
                                @method('DELETE')
                                {{-- U19: Delete turns on only once the name is typed exactly. --}}
                                <x-field name="confirm_name" :placeholder="$event->name" :label="__('events.delete_confirm_label') . ': ' . $event->name" autocomplete="off" required x-model="typed" />
                                <div class="flex justify-end gap-2.5 pt-1.5">
                                    <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                                    <x-button type="submit" variant="destructive" x-bind:disabled="typed !== {{ \Illuminate\Support\Js::from($event->name) }}">{{ __('events.delete') }}</x-button>
                                </div>
                            </form>
                        </x-confirm-dialog>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.host>
