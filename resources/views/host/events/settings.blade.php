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
            <h2 class="font-display text-xl font-semibold">{{ __('events.general') }}</h2>
            <x-field name="name" :label="__('events.name')" :value="$event->name" required maxlength="100" />
            <x-field name="slug" :label="__('events.link')" :value="$event->slug" :prefix="parse_url(config('app.url'), PHP_URL_HOST) . '/'" :hint="__('events.link_hint')" required maxlength="60" autocapitalize="none" spellcheck="false" />
            <h2 class="pt-2 font-display text-xl font-semibold">{{ __('events.screens') }}</h2>
            <x-theme-choice :value="$event->screen_theme->value" :themes="$themes" :hint="__('events.screen_theme_hint')" />
            <x-toggle name="show_on_devices" :label="__('events.show_on_devices')" :hint="__('events.show_on_devices_hint')" :checked="$event->show_on_devices" />
            <div class="flex justify-end pt-2">
                <x-button type="submit">{{ __('events.save') }}</x-button>
            </div>
        </form>

        <div class="flex flex-col gap-5">
            <section class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-labelledby="status-heading">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="status-heading" class="font-display text-xl font-semibold">{{ __('events.status') }}</h2>
                    <x-chip :tone="match ($event->status) { EventStatus::Open => 'success', EventStatus::Draft => 'neutral', EventStatus::Finished => 'warning' }" :dot="$event->status === EventStatus::Open">
                        {{ __('host.event_status.' . $event->status->value) }}
                    </x-chip>
                </div>
                @if ($event->status === EventStatus::Draft)
                    <p class="text-sm text-muted">{{ __('events.open_event_help') }}</p>
                    <form method="POST" action="{{ route('host.events.open', $event) }}">
                        @csrf
                        <x-button type="submit">{{ __('events.open_event') }}</x-button>
                    </form>
                @endif
                <div class="flex flex-col gap-2 border-t border-line-soft pt-4">
                    <p class="text-sm font-semibold">{{ __('events.join_lock') }}</p>
                    <p class="text-sm text-muted">{{ $event->join_locked_at ? __('events.join_locked_state') : __('events.join_lock_help') }}</p>
                    <form method="POST" action="{{ route('host.events.join-lock', $event) }}">
                        @csrf
                        <input type="hidden" name="locked" value="{{ $event->join_locked_at ? '0' : '1' }}">
                        <x-button type="submit" variant="secondary">{{ $event->join_locked_at ? __('events.unlock') : __('events.lock') }}</x-button>
                    </form>
                </div>
            </section>

            <section class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-labelledby="cohosts-heading">
                <h2 id="cohosts-heading" class="font-display text-xl font-semibold">{{ __('events.cohosts') }}</h2>
                <p class="text-sm text-muted">{{ __('events.cohosts_help') }}</p>
                @forelse ($cohosts as $cohost)
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex flex-col">
                            <span class="font-bold">{{ $cohost->name }}</span>
                            <span class="text-sm text-muted">{{ $cohost->email }}</span>
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
                        <x-field name="email" type="email" :label="__('events.cohost_email')" autocomplete="off" />
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
                            <form method="POST" action="{{ route('host.events.reopen', $event) }}">
                                @csrf
                                <x-button type="submit" variant="secondary">{{ __('events.reopen') }}</x-button>
                            </form>
                        </div>
                    @endcan
                @else
                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-semibold">{{ __('events.close') }}</p>
                        <p class="text-sm text-muted">{{ __('events.close_help') }}</p>
                        <x-confirm-dialog :title="__('events.close') . '?'" :trigger="__('events.close')">
                            <p class="text-[15px] text-muted">{{ __('events.close_confirm', ['name' => $event->name]) }}</p>
                            <form method="POST" action="{{ route('host.events.close', $event) }}" class="flex justify-end gap-2.5 pt-1.5">
                                @csrf
                                <x-button variant="secondary" formmethod="dialog" type="submit">{{ __('ui.cancel') }}</x-button>
                                <x-button type="submit">{{ __('events.close') }}</x-button>
                            </form>
                        </x-confirm-dialog>
                    </div>
                @endif

                @if ($isOwner)
                    <form method="POST" action="{{ route('host.events.transfer', $event) }}" class="flex flex-col gap-3 border-t border-line-soft pt-4" novalidate>
                        @csrf
                        <p class="text-sm font-semibold">{{ __('events.transfer') }}</p>
                        <p class="text-sm text-muted">{{ __('events.transfer_help') }}</p>
                        <x-field name="new_owner_email" type="email" :label="__('events.new_owner_email')" autocomplete="off" />
                        <x-button type="submit" variant="secondary" class="self-start">{{ __('events.transfer') }}</x-button>
                    </form>

                    <div class="flex flex-col gap-2 border-t border-line-soft pt-4">
                        <p class="text-sm font-semibold">{{ __('events.delete') }}</p>
                        <p class="text-sm text-muted">{{ __('events.delete_help') }}</p>
                        <x-confirm-dialog :title="__('events.delete') . '?'" :trigger="__('events.delete')" trigger-variant="destructive" :open="$errors->has('confirm_name')">
                            <p class="text-[15px] text-muted">{{ __('events.delete_help') }}</p>
                            <form method="POST" action="{{ route('host.events.destroy', $event) }}" class="flex flex-col gap-3" novalidate>
                                @csrf
                                @method('DELETE')
                                <x-field name="confirm_name" :label="__('events.delete_confirm_label') . ': ' . $event->name" autocomplete="off" />
                                <div class="flex justify-end gap-2.5 pt-1.5">
                                    <x-button variant="secondary" formmethod="dialog" type="submit" formnovalidate>{{ __('ui.cancel') }}</x-button>
                                    <x-button type="submit" variant="destructive">{{ __('events.delete') }}</x-button>
                                </div>
                            </form>
                        </x-confirm-dialog>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.host>
