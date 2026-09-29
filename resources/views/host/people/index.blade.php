@php
    $locked = $event->names_locked_at !== null;
    $linkBase = url('/' . $event->slug . '/j') . '/';
@endphp
<x-layouts.host :title="__('people.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, route('host.events.edit', $event)], [__('people.title'), null]]">
    @unless ($locked)
        <x-slot:actions>
            <x-button variant="secondary" :href="route('host.events.people.import.create', $event)">{{ __('people.import') }}</x-button>
        </x-slot:actions>
    @endunless

    @if ($locked)
        <div role="status" class="rounded-card bg-warning px-4 py-3 text-sm font-semibold text-on-warning">{{ __('people.locked_banner') }}</div>
    @endif
    @error('names')
        <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
    @enderror

    <div class="flex flex-wrap items-end justify-between gap-3">
        <nav class="flex flex-wrap gap-2" aria-label="{{ __('people.status') }}">
            @foreach (['all', 'joined', 'waiting'] as $key)
                <a href="{{ route('host.events.people.index', [$event, ...($key === 'all' ? [] : ['filter' => $key])]) }}"
                    @if ($filter === $key) aria-current="page" @endif
                    @class([
                        'inline-flex h-11 items-center rounded-control px-3.5 text-sm font-semibold',
                        'bg-inverse text-on-inverse' => $filter === $key,
                        'border border-line-strong bg-surface text-ink hover:bg-subtle' => $filter !== $key,
                    ])>{{ __('people.' . $key) }} · {{ $counts[$key] }}</a>
            @endforeach
        </nav>

        @unless ($locked)
            <form method="POST" action="{{ route('host.events.people.store', $event) }}" class="flex items-end gap-2.5" novalidate>
                @csrf
                <input type="hidden" name="form" value="add-person">
                <x-field name="name" :label="__('people.name')" id="add-person-name" :submitted="old('form') === 'add-person'" maxlength="100" autocomplete="off" />
                <x-button type="submit">{{ __('people.add') }}</x-button>
            </form>
        @endunless
    </div>

    <div class="overflow-x-auto rounded-card bg-surface">
        @if ($people->isEmpty())
            <p class="px-6 py-10 text-center text-muted">{{ __('people.empty') }}</p>
        @else
            <table class="w-full min-w-[760px] text-left text-[15px]">
                <thead class="border-b border-line-soft text-xs font-bold tracking-[0.06em] text-muted uppercase">
                    <tr>
                        <th scope="col" class="px-5 py-3">{{ __('people.name') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('people.status') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('people.link') }}</th>
                        <th scope="col" class="px-5 py-3"><span class="sr-only">{{ __('people.status') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($people as $person)
                        <tr class="border-b border-line-soft last:border-0">
                            <td class="px-5 py-3">
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
                                    <span class="font-bold">{{ $person->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($person->claimed_at)
                                    <x-chip tone="success" dot>{{ __('people.status_joined') }}</x-chip>
                                @else
                                    <x-chip>{{ __('people.status_waiting') }}</x-chip>
                                @endif
                            </td>
                            <td class="px-4 py-3" x-data="{ copied: false }">
                                <span class="font-mono text-sm text-muted">/{{ $event->slug }}/j/{{ $person->join_token }}</span>
                                <button type="button" class="ml-2 min-h-11 text-sm font-semibold text-accent"
                                    @click="navigator.clipboard.writeText(@js($linkBase . $person->join_token)).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                                    x-text="copied ? @js(__('people.copied')) : @js(__('people.copy'))">{{ __('people.copy') }}</button>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-1 text-sm font-semibold">
                                    @unless ($locked)
                                        <x-button variant="link" :href="route('host.events.people.index', [$event, 'edit' => $person->id])">{{ __('people.rename') }}</x-button>
                                    @endunless
                                    @if ($person->claimed_at)
                                        <x-confirm-dialog :title="__('people.release') . '?'" :trigger="__('people.release')" trigger-variant="link">
                                            <p class="text-[15px] text-muted">{{ __('people.release_confirm', ['name' => $person->name]) }}</p>
                                            <form method="POST" action="{{ route('host.events.people.release-claim', [$event, $person]) }}" class="flex justify-end gap-2.5 pt-1.5">
                                                @csrf
                                                <x-button variant="secondary" formmethod="dialog" type="submit">{{ __('ui.cancel') }}</x-button>
                                                <x-button type="submit">{{ __('people.release') }}</x-button>
                                            </form>
                                        </x-confirm-dialog>
                                    @endif
                                    <x-confirm-dialog :title="__('people.new_link') . '?'" :trigger="__('people.new_link')" trigger-variant="link">
                                        <p class="text-[15px] text-muted">{{ __('people.new_link_confirm', ['name' => $person->name]) }}</p>
                                        <form method="POST" action="{{ route('host.events.people.regenerate-link', [$event, $person]) }}" class="flex justify-end gap-2.5 pt-1.5">
                                            @csrf
                                            <x-button variant="secondary" formmethod="dialog" type="submit">{{ __('ui.cancel') }}</x-button>
                                            <x-button type="submit">{{ __('people.new_link') }}</x-button>
                                        </form>
                                    </x-confirm-dialog>
                                    @unless ($locked)
                                        <form method="POST" action="{{ route('host.events.people.destroy', [$event, $person]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="link-destructive">{{ __('people.delete') }}</x-button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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
