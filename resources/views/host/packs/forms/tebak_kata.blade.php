@php
    $answer = (string) old('answer_text', $question?->kata?->answer_text ?? '');
    $open = array_map('intval', (array) old('initial_open_indexes', $question?->kata?->initial_open_indexes ?? []));
    $points = (string) old('points', $question?->points ?? 1);
    $promptError = $errors->first('prompt');
    $boxesError = $errors->first('initial_open_indexes');
@endphp
<div class="flex flex-col gap-1.5">
    <label for="field-prompt" class="text-sm font-semibold">{{ __('packs.kata.prompt') }}</label>
    <textarea id="field-prompt" name="prompt" rows="2" maxlength="500" @if ($promptError) aria-describedby="prompt-error" @endif
        @class(['rounded-control border bg-field px-3 py-2.5 text-[15px] text-ink', 'border-on-danger' => $promptError, 'border-line-strong' => ! $promptError])>{{ old('prompt', $question?->kata?->prompt) }}</textarea>
    @if ($promptError)
        <p id="prompt-error" class="text-sm font-semibold text-on-danger">{{ $promptError }}</p>
    @endif
</div>

<div x-data="kataBoxes({ answer: @js($answer), open: @js($open) })" class="flex flex-col gap-4">
    <x-field name="answer_text" :label="__('packs.kata.answer')" :value="$answer" :hint="__('packs.kata.answer_hint')" maxlength="40"
        autocomplete="off" x-model="answer" @input="pruneOpen()" />

    <fieldset class="flex flex-col gap-2">
        <legend class="mb-1 text-sm font-semibold">{{ __('packs.kata.boxes') }}</legend>
        <template x-if="boxes">
            <div class="flex flex-col gap-2">
                <template x-for="(word, w) in boxes.words" :key="w">
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="cell in word" :key="cell.box ?? ('p' + w + cell.char)">
                            <span>
                                <template x-if="cell.box === null">
                                    <span class="flex h-11 w-6 items-center justify-center font-display text-xl font-bold" x-text="cell.char"></span>
                                </template>
                                <template x-if="cell.box !== null">
                                    <button type="button" @click="toggle(cell.box)" :aria-pressed="isOpen(cell.box).toString()"
                                        :aria-label="@js(__('packs.kata.box_label', ['number' => '__N__', 'char' => '__C__'])).replace('__N__', cell.box + 1).replace('__C__', cell.char)"
                                        class="flex size-11 items-center justify-center rounded-control border-2 font-display text-xl font-bold"
                                        :class="isOpen(cell.box) ? 'border-accent bg-accent text-on-accent' : 'border-line-strong bg-subtle text-muted'"
                                        x-text="cell.char"></button>
                                </template>
                            </span>
                        </template>
                    </div>
                </template>
                <p class="text-sm text-muted">
                    <span x-text="@js(__('packs.kata.boxes_count', ['open' => '__O__', 'total' => '__T__'])).replace('__O__', open.length).replace('__T__', boxes.count)"></span>.
                    {{ __('packs.kata.boxes_hint') }}
                </p>
            </div>
        </template>
        <template x-if="! boxes">
            <p class="text-sm text-muted">{{ __('packs.kata.boxes_invalid') }}</p>
        </template>
        <template x-for="box in open" :key="box">
            <input type="hidden" name="initial_open_indexes[]" :value="box">
        </template>
        @if ($boxesError)
            <p class="text-sm font-semibold text-on-danger">{{ $boxesError }}</p>
        @endif
    </fieldset>
</div>

<fieldset class="flex flex-col gap-1.5">
    <legend class="mb-1.5 text-sm font-semibold">{{ __('packs.points') }}</legend>
    <div class="inline-flex w-fit gap-1 rounded-control border border-line-strong bg-field p-1">
        @foreach (['0', '1', '2'] as $value)
            <label class="cursor-pointer">
                <input type="radio" name="points" value="{{ $value }}" class="peer sr-only" @checked($points === $value)>
                <span class="flex size-10 items-center justify-center rounded-[8px] text-sm font-bold text-muted peer-checked:bg-inverse peer-checked:text-on-inverse peer-focus-visible:outline-3 peer-focus-visible:outline-accent">{{ $value }}</span>
            </label>
        @endforeach
    </div>
    <p class="text-sm text-muted">{{ __('packs.points_hint') }}</p>
    @error('points')
        <p class="text-sm font-semibold text-on-danger">{{ $message }}</p>
    @enderror
</fieldset>
