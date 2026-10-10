@php
    use Illuminate\Support\Js;

    // U26: a refused Create event opens again at the step of its first error, with what was sent.
    $sent = old('form') === 'create-event' && $errors->any();
    $packErrors = collect($errors->keys())->contains(fn (string $key): bool => str_starts_with($key, 'pack_ids'));
    $start = ! $sent || $errors->hasAny(['name', 'slug', 'screen_theme', 'show_on_devices']) ? 1 : ($errors->has('names') ? 2 : ($packErrors ? 3 : 4));
    $titles = $packs->flatten()->mapWithKeys(fn ($pack) => [$pack->id => $pack->title]);
    $picked = $sent ? array_values(array_filter((array) old('pack_ids', []), fn ($id) => is_string($id) && $titles->has($id))) : [];
    $steps = ['details' => __('events.step_details'), 'names' => __('events.step_names'), 'games' => __('events.step_games'), 'review' => __('events.step_review')];
    $texts = [
        'imported' => ['one' => trans_choice('events.imported_names', 1, ['count' => '__N__', 'file' => '__F__']), 'other' => trans_choice('events.imported_names', 2, ['count' => '__N__', 'file' => '__F__'])],
        'count' => ['one' => trans_choice('people.names_count', 1, ['count' => '__N__']), 'other' => trans_choice('people.names_count', 2, ['count' => '__N__'])],
        'import_failed' => __('events.import_failed'),
        // The Add names checks (name-batch.js), in the words of the server.
        'line' => __('people.on_line', ['line' => '__L__', 'problem' => '__P__']),
        'existing' => __('people.duplicate_existing', ['name' => '__N__']),
        'repeat' => __('people.duplicate_line', ['line' => '__R__', 'name' => '__N__']),
        'too_long' => __('people.too_long', ['max' => \App\People\PersonName::MAX_LENGTH]),
        // The review summary of step 1.
        'themes' => __('events.themes'),
        'devices' => ['on' => __('events.devices_on'), 'off' => __('events.devices_off')],
    ];
@endphp
{{-- U26: New event in four steps; nothing is saved before Create event. Every step stays in the
     form (only hidden), so Create event sends them all; the server checks them all again. --}}
<form method="POST" action="{{ route('host.events.store') }}" novalidate x-ref="wizardForm"
    :style="'--step-dir: ' + (direction === 'back' ? -1 : 1)"
    x-data="newEventWizard({
        texts: {{ Js::from($texts) }},
        parseUrl: {{ Js::from(route('host.events.names.parse')) }},
        csrf: {{ Js::from(csrf_token()) }},
        start: {{ $start }},
        detailsValid: {{ Js::from($start > 1) }},
        names: {{ Js::from($sent ? (string) old('names', '') : '') }},
        picked: {{ Js::from($picked) }},
        titles: {{ Js::from($titles) }},
    })"
    x-init="$el.closest('dialog')?.addEventListener('cancel', (event) => { if (! leaving && dirty(details())) { event.preventDefault(); askDiscard() } })"
    class="flex flex-col gap-5">
    @csrf
    <input type="hidden" name="form" value="create-event">

    {{-- The steps; any of them opens once step 1 is valid. --}}
    <ol class="flex flex-wrap items-center gap-2 text-body-s font-semibold" aria-label="{{ __('events.steps') }}">
        @foreach ($steps as $key => $label)
            @php $number = $loop->iteration; @endphp
            @unless ($loop->first)
                <li aria-hidden="true" class="h-px w-6 bg-line-strong"></li>
            @endunless
            <li>
                <button type="button" @click="go({{ $number }})" :disabled="! canGo({{ $number }})" :aria-current="step === {{ $number }} ? 'step' : null"
                    data-step="{{ $key }}" class="inline-flex min-h-11 items-center gap-2 rounded-control px-1 disabled:cursor-not-allowed disabled:opacity-50"
                    :class="step === {{ $number }} ? 'text-ink' : 'text-muted hover:text-ink'">
                    <span class="flex size-6 items-center justify-center rounded-full text-caption" :class="step === {{ $number }} ? 'bg-accent text-on-accent' : 'bg-subtle'" aria-hidden="true">{{ $number }}</span>{{ $label }}
                </button>
            </li>
        @endforeach
    </ol>

    {{-- ① Name and link: the fields New event had before. --}}
    {{-- A field the host changes drops its old server error; the server checks it again (live check, Create). --}}
    <div x-show="step === 1" x-transition:enter="step-in" x-transition:enter-start="step-from" x-ref="details" @input="$event.target.removeAttribute('aria-invalid'); refreshDetails(quietlyValid($refs.details))" class="flex flex-col gap-4">
        {{-- U19: every send of this form keeps what was typed, the live checks too; with $sent (errors only) a
             field the server drew again after a check came back empty. --}}
        <x-forms.new-event-fields :submitted="old('form') === 'create-event'" />
    </div>

    {{-- ② Names: typed one per line, and the names of a CSV file added under them. --}}
    <div x-show="step === 2" x-transition:enter="step-in" x-transition:enter-start="step-from" x-cloak class="flex flex-col gap-2">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <label for="new-event-names" class="text-body-s font-semibold">{{ __('people.names_label') }}</label>
            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-control border border-field-line bg-surface px-4 text-body-s font-bold text-ink hover:bg-subtle"
                :aria-busy="importing.toString()">
                <x-icon name="upload" /> {{ __('events.import_csv') }}
                <input type="file" accept=".csv,text/csv,text/plain" class="sr-only" data-test="new-event-file"
                    @change="importFile($event.target.files[0]); $event.target.value = ''">
            </label>
        </div>
        <textarea id="new-event-names" name="names" rows="8" x-model="text" autocomplete="off" placeholder="{{ __('examples.names') }}"
            aria-describedby="new-event-names-hint new-event-names-check"
            class="max-h-[40dvh] rounded-control border border-field-line bg-field px-3 py-2.5 text-body text-ink"></textarea>
        <p id="new-event-names-hint" class="text-body-s text-muted">{{ __('events.names_step_hint') }}</p>
        <p x-show="importError !== ''" x-cloak x-text="importError" role="alert" class="text-body-s font-semibold text-on-danger"></p>
        <p x-show="importNote !== ''" x-cloak class="flex flex-wrap items-center gap-2 text-body-s text-on-success">
            <span x-text="importNote"></span>
            <button type="button" @click="undoImport()" class="min-h-11 font-semibold text-link underline-offset-4 hover:underline">{{ __('events.undo_import') }}</button>
        </p>
        <div id="new-event-names-check" aria-live="polite" class="flex flex-col gap-1 text-body-s">
            <p x-show="nameCheck.names.length > 0" x-text="nameCount" class="text-muted"></p>
            <template x-for="problem in nameCheck.problems" :key="problem.line">
                <p class="font-semibold text-on-danger" x-text="lineProblem(problem)"></p>
            </template>
            <p x-show="nameCheck.tooMany" class="font-semibold text-on-danger">{{ __('people.too_many', ['max' => \App\People\PersonName::MAX_PER_EVENT]) }}</p>
        </div>
        @error('names')
            @foreach ($errors->get('names') as $message)
                <p class="text-body-s font-semibold text-on-danger">{{ $message }}</p>
            @endforeach
        @enderror
    </div>

    {{-- ③ Games: the pack list of Add games, picked in order. --}}
    <div x-show="step === 3" x-transition:enter="step-in" x-transition:enter-start="step-from" x-cloak class="flex flex-col gap-3">
        @if ($packs->isEmpty())
            <p class="text-body text-muted">{{ __('games.no_packs') }}</p>
        @else
            <x-pack-list :packs="$packs" :types="$types" pick />
            @include('host.partials.pack-picks')
        @endif
        <template x-for="id in picked" :key="id"><input type="hidden" name="pack_ids[]" :value="id"></template>
        @if ($packErrors)
            <p class="text-body-s font-semibold text-on-danger">{{ __('events.games_refused') }}</p>
        @endif
    </div>

    {{-- ④ Review: what Create event saves, each part with the way back to its step. --}}
    <div x-show="step === 4" x-transition:enter="step-in" x-transition:enter-start="step-from" x-cloak class="flex flex-col gap-1">
        <dl class="flex flex-col divide-y divide-line-soft">
            <div class="flex flex-wrap items-start justify-between gap-3 py-3">
                <dt class="w-24 text-body-s font-semibold text-muted">{{ __('events.review_event') }}</dt>
                <dd class="flex min-w-0 grow flex-col gap-0.5 text-body">
                    <span class="font-semibold" x-text="step === 4 ? details().name : ''"></span>
                    <span class="text-body-s text-muted" x-text="step === 4 ? @js(parse_url(config('app.url'), PHP_URL_HOST) . '/') + details().slug : ''"></span>
                    <span class="text-body-s text-muted" x-text="step === 4 ? details().summary : ''"></span>
                </dd>
                <button type="button" @click="go(1)" class="min-h-11 text-body-s font-semibold text-link underline-offset-4 hover:underline">{{ __('events.edit_step') }}</button>
            </div>
            <div class="flex flex-wrap items-start justify-between gap-3 py-3">
                <dt class="w-24 text-body-s font-semibold text-muted">{{ __('events.review_names') }}</dt>
                <dd class="flex min-w-0 grow flex-col gap-0.5 text-body">
                    <span x-text="nameCheck.names.length > 0 ? nameCount : @js(__('events.no_names_yet'))"></span>
                    <span x-show="! namesOk" class="text-body-s font-semibold text-on-danger">{{ __('events.names_to_fix') }}</span>
                </dd>
                <button type="button" @click="go(2)" class="min-h-11 text-body-s font-semibold text-link underline-offset-4 hover:underline">{{ __('events.edit_step') }}</button>
            </div>
            <div class="flex flex-wrap items-start justify-between gap-3 py-3">
                <dt class="w-24 text-body-s font-semibold text-muted">{{ __('events.review_games') }}</dt>
                <dd class="min-w-0 grow text-body" x-text="picked.length > 0 ? chips().map((chip) => chip.number + '. ' + chip.title).join(' · ') : @js(__('events.no_games_yet'))"></dd>
                <button type="button" @click="go(3)" class="min-h-11 text-body-s font-semibold text-link underline-offset-4 hover:underline">{{ __('events.edit_step') }}</button>
            </div>
        </dl>
        <p class="text-body-s text-muted">{{ __('events.review_note') }}</p>
    </div>

    {{-- U2: Back on the left, the way on at the right. --}}
    <div class="flex flex-wrap items-center justify-between gap-2.5 pt-1.5">
        <div>
            <x-button variant="secondary" type="button" x-show="step > 1" @click="back()">{{ __('people.back') }}</x-button>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <x-button variant="secondary" type="button" @click="requestClose()">{{ __('ui.cancel') }}</x-button>
            <x-button type="button" x-show="step === 1" @click="nextFromDetails($refs.details)" data-test="wizard-next">{{ __('events.next') }}</x-button>
            <x-button type="button" x-show="step === 2 || step === 3" x-bind:disabled="step === 2 && ! namesOk" @click="next()" data-test="wizard-next">
                <span x-text="skippable ? @js(__('events.skip')) : @js(__('events.next'))">{{ __('events.next') }}</span>
            </x-button>
            <x-button type="submit" x-show="step === 4" x-bind:disabled="! canCreate" data-test="wizard-create">{{ __('events.create') }}</x-button>
        </div>
    </div>

    {{-- U24: leaving after typing asks first. --}}
    <dialog x-ref="discard" class="m-auto w-[min(400px,calc(100vw-2rem))] rounded-panel bg-surface p-0 text-left text-ink shadow-2xl">
        <div class="flex flex-col gap-3 p-5.5">
            <h2 class="font-display text-title-s font-bold">{{ __('events.discard_title') }}</h2>
            <p class="text-body text-muted">{{ __('events.discard_body') }}</p>
            <div class="flex justify-end gap-2.5 pt-1.5">
                <x-button variant="secondary" type="button" @click="$refs.discard.close()">{{ __('people.keep_editing') }}</x-button>
                <x-button variant="destructive" type="button" @click="$refs.discard.close(); discard()">{{ __('people.discard') }}</x-button>
            </div>
        </div>
    </dialog>
</form>
