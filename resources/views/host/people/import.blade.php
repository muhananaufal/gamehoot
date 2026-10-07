@php
    $reviewing = $rows !== null;
    $problemCount = $reviewing ? count(array_filter($rows, fn (array $row): bool => $row['problem'] !== null)) : 0;
    // B-4: Back (and step 1) return to Upload, naming the last file; the file is picked again.
    $backUrl = route('host.events.people.import.create', array_filter(['event' => $event, 'last' => $fileName]));
@endphp
<x-layouts.host :heading="false" :title="__('people.import_title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, route('host.events.edit', $event)], [__('people.title'), route('host.events.people.index', $event)], [__('people.import'), null]]">
    {{-- B-4, U13: what the Import modal shows over the Names page: two steps, Upload and Review.
         Save is a button of Review; once saved the modal closes on the updated list. --}}
    <div up-main="modal" class="flex flex-col gap-5 p-6"
        @if ($reviewing && $rows !== [])
            x-data="nameReview({
                names: @js(array_column($rows, 'name')),
                existing: @js($event->people()->pluck('name', 'name_normalized')),
                room: @js($room),
                labels: @js(collect(['review' => 'people.review', 'ready' => 'people.ready', 'duplicates' => 'people.duplicates', 'save' => 'people.save_names'])
                    ->map(fn (string $key): array => [
                        'one' => trans_choice($key, 1, ['count' => '__N__']),
                        'other' => trans_choice($key, 2, ['count' => '__N__']),
                    ])),
            })"
        @endif>
        <div class="flex flex-col gap-3">
            <h2 class="font-display text-[22px] font-bold">{{ __('people.import_title') }}</h2>
            <ol class="flex flex-wrap items-center gap-2 text-sm font-semibold" aria-label="{{ __('people.import_steps') }}">
                <li>
                    @if ($reviewing)
                        <a href="{{ $backUrl }}" @if ($rows !== []) @click="back($event)" @endif class="inline-flex min-h-11 items-center gap-2 rounded-control px-1 text-muted hover:text-ink">
                            <span class="flex size-6 items-center justify-center rounded-full bg-subtle text-xs" aria-hidden="true">1</span>{{ __('people.step_upload') }}
                        </a>
                    @else
                        <span aria-current="step" class="inline-flex min-h-11 items-center gap-2 px-1">
                            <span class="flex size-6 items-center justify-center rounded-full bg-accent text-xs text-on-accent" aria-hidden="true">1</span>{{ __('people.step_upload') }}
                        </span>
                    @endif
                </li>
                <li aria-hidden="true" class="h-px w-8 bg-line-strong"></li>
                <li>
                    <span @if ($reviewing) aria-current="step" @endif @class(['inline-flex min-h-11 items-center gap-2 px-1', 'text-muted' => ! $reviewing])>
                        <span @class(['flex size-6 items-center justify-center rounded-full text-xs', 'bg-accent text-on-accent' => $reviewing, 'bg-subtle' => ! $reviewing]) aria-hidden="true">2</span>{{ __('people.step_review') }}
                    </span>
                </li>
            </ol>
        </div>

        @if (! $reviewing)
            <form method="POST" action="{{ route('host.events.people.import.store', $event) }}" enctype="multipart/form-data" class="flex flex-col gap-4" novalidate>
                @csrf
                @error('names')
                    <div role="alert" class="rounded-control bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
                @enderror
                {{-- B-4: the format the importer reads, and a file that follows it. --}}
                <div class="flex flex-col gap-2 rounded-control bg-subtle p-4 text-sm">
                    <p class="font-semibold">{{ __('people.format_title') }}</p>
                    <ul class="flex list-disc flex-col gap-1 pl-5 text-muted">
                        @foreach (['format_rows', 'format_header', 'format_separator', 'format_limits'] as $rule)
                            <li>{{ __('people.' . $rule, ['length' => \App\People\PersonName::MAX_LENGTH, 'max' => \App\Http\Requests\Host\PersonNameRequest::MAX_NAMES]) }}</li>
                        @endforeach
                    </ul>
                    <x-button variant="link" :href="route('host.events.people.import.template', $event)" download class="self-start">{{ __('people.download_template') }}</x-button>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="import-file" class="text-sm font-semibold">{{ __('people.file') }}</label>
                    <input id="import-file" type="file" name="file" accept=".csv,text/csv,text/plain" aria-describedby="import-file-hint"
                        class="min-h-11 rounded-control border border-line-strong bg-field p-2 text-[15px] file:mr-3 file:rounded-[8px] file:border-0 file:bg-subtle file:px-3 file:py-2 file:font-semibold file:text-ink">
                    <p id="import-file-hint" class="text-sm text-muted">
                        {{ $last !== null ? __('people.last_file', ['name' => $last]) : __('people.file_hint') }}
                    </p>
                    @error('file')
                        <p class="text-sm font-semibold text-on-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end gap-2.5 pt-1">
                    <x-button variant="secondary" :href="route('host.events.people.index', $event)" up-dismiss="">{{ __('ui.cancel') }}</x-button>
                    <x-button type="submit">{{ __('people.upload') }}</x-button>
                </div>
            </form>
        @elseif ($rows === [])
            {{-- U10: a file without names says so, with the way back. --}}
            <x-empty-state :title="__('people.no_names_in_file')" :body="__('people.no_names_in_file_hint')" class="bg-subtle">
                <x-button variant="secondary" :href="$backUrl">{{ __('people.back') }}</x-button>
            </x-empty-state>
        @else
            <form method="POST" action="{{ route('host.events.people.import.confirm', $event) }}" novalidate @submit="guard?.allow()" class="flex flex-col gap-4">
                @csrf
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-display text-xl font-semibold" x-text="label('review', rows.length)">{{ trans_choice('people.review', count($rows)) }}</h3>
                        <p class="text-sm text-muted">
                            {{ $fileName }}@if ($separator) · {{ __('people.separator', ['separator' => $separator]) }}@endif
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <x-chip tone="success" x-text="label('ready', rows.length - problemCount)">
                            {{ __('people.ready', ['count' => count($rows) - $problemCount]) }}
                        </x-chip>
                        <x-chip tone="danger" x-show="problemCount > 0" x-text="label('duplicates', problemCount)">
                            {{ trans_choice('people.duplicates', $problemCount) }}
                        </x-chip>
                    </div>
                </div>

                {{-- B-4: a status filter and a search hide rows only; every row is still saved. The row
                     numbers stay those of the file, so "Same as row 12" can still be found. --}}
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <x-select :options="[
                        ['value' => 'all', 'label' => __('people.filter_all')],
                        ['value' => 'ready', 'label' => __('people.filter_ready')],
                        ['value' => 'fix', 'label' => __('people.filter_fix')],
                    ]" :label="__('people.filter_review')" x-model="filter" />
                    <label class="relative flex w-full items-center sm:w-64">
                        <x-icon name="search" class="pointer-events-none absolute left-3 text-muted" />
                        <input type="search" x-model.debounce.150ms="q" aria-label="{{ __('people.search_review') }}" placeholder="{{ __('people.search_review') }}" autocomplete="off"
                            class="h-11 w-full rounded-control border border-line-strong bg-field pr-3 pl-10 text-[15px] text-ink">
                    </label>
                </div>

                {{-- The list scrolls inside the modal; the buttons stay in view below it. --}}
                <div class="max-h-[50dvh] overflow-auto rounded-control border border-line-soft">
                    <table class="w-full min-w-[560px] text-left text-[15px]">
                        <thead class="sticky top-0 border-b border-line-soft bg-surface text-xs font-bold tracking-[0.06em] text-muted uppercase">
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
                                    <td class="px-3 py-2"><input type="text" name="names[]" placeholder="{{ __('examples.person_name') }}" value="{{ $row['name'] }}" aria-label="{{ __('people.name') }} {{ $index + 1 }}" class="h-11 w-full rounded-control border border-line-strong bg-field px-3"></td>
                                    <td @class(['px-3 py-2 text-sm', 'font-semibold text-on-danger' => $row['problem'] !== null, 'text-muted' => $row['problem'] === null])>{{ $row['problem']?->message() ?? '—' }}</td>
                                    <td class="px-3 py-2"></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <template x-if="true">
                            <tbody>
                                <template x-for="(row, index) in rows" :key="row.id">
                                    <tr x-show="shown(row, index)" class="border-b border-line-soft">
                                        <td class="px-3 py-2 tabular-nums" x-text="index + 1"></td>
                                        <td class="px-3 py-2">
                                            <input type="text" name="names[]" placeholder="{{ __('examples.person_name') }}" x-model="row.name" required maxlength="100" :aria-label="@js(__('people.name')) + ' ' + (index + 1)"
                                                class="h-11 w-full rounded-control border bg-field px-3"
                                                :class="problemAt(index) ? 'border-on-danger' : 'border-line-strong'">
                                        </td>
                                        <td class="px-3 py-2 text-sm" :class="problemAt(index) ? 'font-semibold text-on-danger' : 'text-muted'"
                                            x-text="problemAt(index) === null ? '—' : problemAt(index).tooLong ? @js(__('people.too_long', ['max' => \App\People\PersonName::MAX_LENGTH])) : (problemAt(index).existing !== undefined
                                                ? @js(__('people.duplicate_existing', ['name' => '__N__'])).replace('__N__', problemAt(index).existing)
                                                : @js(__('people.duplicate_row', ['row' => '__R__', 'name' => '__N__'])).replace('__R__', problemAt(index).row).replace('__N__', row.name.trim()))"></td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" @click="remove(row.id)" class="min-h-11 text-sm font-semibold text-on-danger"
                                                :aria-label="@js(__('people.remove_row', ['row' => '__R__'])).replace('__R__', index + 1)">{{ __('people.remove') }}</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </template>
                    </table>
                    <div x-show="nothingShown" x-cloak role="status" class="flex flex-col items-center gap-2 px-4 py-8 text-center text-sm text-muted">
                        <p>{{ __('people.no_rows_match') }}</p>
                        <x-button variant="link" type="button" @click="reset()">{{ __('ui.reset_filters') }}</x-button>
                    </div>
                </div>

                @if ($errors->has('names') || $errors->has('names.*'))
                    {{-- The first real problem the server found, not a generic sentence. --}}
                    <p role="alert" class="text-sm font-semibold text-on-danger">{{ $errors->first('names') ?: $errors->first('names.*') }}</p>
                @endif

                {{-- B-3: the review says up front when the list no longer fits. --}}
                <p @if (count($rows) <= $room) x-cloak @endif x-show="overLimit" role="status" class="text-sm font-semibold text-on-danger">{{ trans_choice('people.room', $room, ['max' => \App\Http\Requests\Host\PersonNameRequest::MAX_NAMES]) }}</p>

                {{-- U2: Back on the left, Save on the right. --}}
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <x-button variant="secondary" :href="$backUrl" data-test="import-back" @click="back($event)">{{ __('people.back') }}</x-button>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <p class="text-sm font-semibold text-on-danger" x-show="problemCount > 0">{{ __('people.fix_to_save') }}</p>
                        {{-- U13: modals have no close button; Cancel asks first after a change (import-guard.js). --}}
                        <x-button variant="secondary" type="button" up-dismiss="" data-test="import-cancel">{{ __('ui.cancel') }}</x-button>
                        <x-button type="submit" x-bind:disabled="problemCount > 0 || overLimit || rows.length === 0"
                            x-text="label('save', rows.length)">
                            {{ trans_choice('people.save_names', count($rows)) }}
                        </x-button>
                    </div>
                </div>

                {{-- U24: leaving after a change asks first (import-guard.js). --}}
                <x-confirm-dialog :title="__('people.discard_title')" :trigger="__('people.discard')" hide-trigger dialog-id="discard-import">
                    <p class="text-[15px] text-muted">{{ __('people.discard_body') }}</p>
                    <div class="flex justify-end gap-2.5 pt-1.5">
                        <x-button variant="secondary" type="button" @click="$el.closest('dialog').close(); pending = null">{{ __('people.keep_editing') }}</x-button>
                        <x-button variant="destructive" type="button" @click="$el.closest('dialog').close(); leave()">{{ __('people.discard') }}</x-button>
                    </div>
                </x-confirm-dialog>
            </form>
        @endif
    </div>
</x-layouts.host>
