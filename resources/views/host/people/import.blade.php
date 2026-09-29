@php
    $reviewing = $rows !== null;
    $problemCount = $reviewing ? count(array_filter($rows, fn (array $row): bool => $row['problem'] !== null)) : 0;
@endphp
<x-layouts.host :title="__('people.import_title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, route('host.events.edit', $event)], [__('people.title'), route('host.events.people.index', $event)], [__('people.import'), null]]">
    <x-slot:actions>
        <x-button variant="secondary" :href="route('host.events.people.index', $event)">{{ __('people.cancel_import') }}</x-button>
    </x-slot:actions>

    <div class="grid gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
        <section class="flex flex-col gap-4 rounded-card bg-surface p-5">
            <div>
                <h2 class="font-display text-xl font-semibold">{{ __('people.import_title') }}</h2>
                <p class="text-sm text-muted">{{ __('people.import_steps') }}</p>
            </div>
            <x-timeline :steps="[
                ['label' => __('people.step_upload'), 'hint' => $reviewing ? $fileName : null, 'state' => $reviewing ? 'done' : 'current'],
                ['label' => __('people.step_review'), 'hint' => $reviewing ? __('people.duplicates_left', ['count' => $problemCount]) : null, 'state' => $reviewing ? 'current' : 'todo'],
                ['label' => __('people.step_save'), 'hint' => __('people.step_save_hint'), 'state' => 'todo'],
            ]" />
        </section>

        @if (! $reviewing)
            <form method="POST" action="{{ route('host.events.people.import.store', $event) }}" enctype="multipart/form-data"
                class="flex max-w-2xl flex-col gap-4 rounded-card bg-surface p-6" novalidate>
                @csrf
                @error('names')
                    <div role="alert" class="rounded-control bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
                @enderror
                <div class="flex flex-col gap-1.5">
                    <label for="import-file" class="text-sm font-semibold">{{ __('people.file') }}</label>
                    <input id="import-file" type="file" name="file" accept=".csv,text/csv,text/plain" aria-describedby="import-file-hint"
                        class="min-h-11 rounded-control border border-line-strong bg-field p-2 text-[15px] file:mr-3 file:rounded-[8px] file:border-0 file:bg-subtle file:px-3 file:py-2 file:font-semibold file:text-ink">
                    <p id="import-file-hint" class="text-sm text-muted">{{ __('people.file_hint') }}</p>
                    @error('file')
                        <p class="text-sm font-semibold text-on-danger">{{ $message }}</p>
                    @enderror
                </div>
                <x-button type="submit" class="self-start">{{ __('people.upload') }}</x-button>
            </form>
        @else
            <form method="POST" action="{{ route('host.events.people.import.confirm', $event) }}" novalidate
                x-data="nameReview({ names: @js(array_column($rows, 'name')), existing: @js($event->people()->pluck('name', 'name_normalized')) })"
                class="flex flex-col gap-4 rounded-card bg-surface p-6">
                @csrf
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-semibold">{{ __('people.review', ['count' => count($rows)]) }}</h2>
                        @if ($separator)
                            <p class="text-sm text-muted">{{ __('people.separator', ['separator' => $separator]) }}</p>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <x-chip tone="success" x-text="@js(__('people.ready', ['count' => '__N__'])).replace('__N__', rows.length - problemCount)">
                            {{ __('people.ready', ['count' => count($rows) - $problemCount]) }}
                        </x-chip>
                        <x-chip tone="danger" x-show="problemCount > 0" x-text="@js(__('people.duplicates', ['count' => '__N__'])).replace('__N__', problemCount)">
                            {{ __('people.duplicates', ['count' => $problemCount]) }}
                        </x-chip>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-[15px]">
                        <thead class="border-b border-line-soft text-xs font-bold tracking-[0.06em] text-muted uppercase">
                            <tr>
                                <th scope="col" class="w-16 px-3 py-2">{{ __('people.row') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('people.name') }}</th>
                                <th scope="col" class="px-3 py-2">{{ __('people.problem') }}</th>
                                <th scope="col" class="px-3 py-2"><span class="sr-only">{{ __('people.remove') }}</span></th>
                            </tr>
                        </thead>
                        {{-- Server render for the first paint and without JS; Alpine takes over the rows. --}}
                        <tbody x-init="$el.remove()">
                            @foreach ($rows as $index => $row)
                                <tr class="border-b border-line-soft">
                                    <td class="px-3 py-2 tabular-nums">{{ $index + 1 }}</td>
                                    <td class="px-3 py-2"><input type="text" name="names[]" value="{{ $row['name'] }}" aria-label="{{ __('people.name') }} {{ $index + 1 }}" class="h-11 w-full rounded-control border border-line-strong bg-field px-3"></td>
                                    <td @class(['px-3 py-2 text-sm', 'font-semibold text-on-danger' => $row['problem'] !== null, 'text-muted' => $row['problem'] === null])>{{ $row['problem']?->message() ?? '—' }}</td>
                                    <td class="px-3 py-2"></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <template x-if="true">
                            <tbody>
                                <template x-for="(row, index) in rows" :key="row.id">
                                    <tr class="border-b border-line-soft">
                                        <td class="px-3 py-2 tabular-nums" x-text="index + 1"></td>
                                        <td class="px-3 py-2">
                                            <input type="text" name="names[]" x-model="row.name" maxlength="100" :aria-label="@js(__('people.name')) + ' ' + (index + 1)"
                                                class="h-11 w-full rounded-control border bg-field px-3"
                                                :class="problemAt(index) ? 'border-on-danger' : 'border-line-strong'">
                                        </td>
                                        <td class="px-3 py-2 text-sm" :class="problemAt(index) ? 'font-semibold text-on-danger' : 'text-muted'"
                                            x-text="problemAt(index) === null ? '—' : (problemAt(index).existing !== undefined
                                                ? @js(__('people.duplicate_existing', ['name' => '__N__'])).replace('__N__', problemAt(index).existing)
                                                : @js(__('people.duplicate_row', ['row' => '__R__'])).replace('__R__', problemAt(index).row))"></td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" @click="remove(row.id)" class="min-h-11 text-sm font-semibold text-on-danger"
                                                :aria-label="@js(__('people.remove_row', ['row' => '__R__'])).replace('__R__', index + 1)">{{ __('people.remove') }}</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </template>
                    </table>
                </div>

                @if ($errors->has('names') || $errors->has('names.*'))
                    <p role="alert" class="text-sm font-semibold text-on-danger">{{ $errors->first('names') ?: __('people.fix_to_save') }}</p>
                @endif

                <div class="flex items-center justify-end gap-3">
                    <p class="text-sm font-semibold text-on-danger" x-show="problemCount > 0">{{ __('people.fix_to_save') }}</p>
                    <x-button type="submit" x-bind:disabled="problemCount > 0 || rows.length === 0"
                        x-text="@js(__('people.save_names', ['count' => '__N__'])).replace('__N__', rows.length)">
                        {{ __('people.save_names', ['count' => count($rows)]) }}
                    </x-button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.host>
