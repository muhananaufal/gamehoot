@php
    $duration = (int) old('duration_seconds', $question?->pentahoot?->duration_seconds ?? 20);
    $promptError = $errors->first('prompt');
@endphp
<div class="flex flex-col gap-1.5">
    <label for="field-prompt" class="text-sm font-semibold">{{ __('packs.pentahoot.prompt') }}</label>
    <textarea id="field-prompt" name="prompt" rows="3" required maxlength="500" aria-describedby="prompt-hint{{ $promptError ? ' prompt-error' : '' }}"
        @class(['rounded-control border bg-field px-3 py-2.5 text-[15px] text-ink', 'border-on-danger' => $promptError, 'border-line-strong' => ! $promptError])>{{ old('prompt', $question?->pentahoot?->prompt) }}</textarea>
    <p id="prompt-hint" class="text-sm text-muted">{{ __('packs.pentahoot.prompt_hint') }}</p>
    @if ($promptError)
        <p id="prompt-error" class="text-sm font-semibold text-on-danger">{{ $promptError }}</p>
    @endif
</div>

{{-- U14: quick times, or Custom with its own seconds field, so it is clear they are one setting. --}}
<fieldset class="flex flex-col gap-2" x-data="{ duration: {{ $duration }}, custom: {{ in_array($duration, [10, 20, 30, 60], true) ? 'false' : 'true' }} }">
    <legend class="mb-1 text-sm font-semibold">{{ __('packs.pentahoot.duration') }}</legend>
    <div class="flex flex-wrap gap-2">
        @foreach ([10, 20, 30, 60] as $preset)
            <button type="button" @click="duration = {{ $preset }}; custom = false" :aria-pressed="(! custom && duration === {{ $preset }}).toString()"
                class="h-11 rounded-control border border-line-strong px-4 text-sm font-semibold"
                :class="! custom && duration === {{ $preset }} ? 'bg-inverse text-on-inverse' : 'bg-surface text-ink hover:bg-subtle'">
                {{ __('packs.seconds', ['count' => $preset]) }}
            </button>
        @endforeach
        <button type="button" @click="custom = true" :aria-pressed="custom.toString()"
            class="h-11 rounded-control border border-line-strong px-4 text-sm font-semibold"
            :class="custom ? 'bg-inverse text-on-inverse' : 'bg-surface text-ink hover:bg-subtle'">{{ __('packs.pentahoot.custom') }}</button>
    </div>
    <div x-show="custom" x-cloak>
        <x-field name="duration_seconds" type="number" :label="__('packs.pentahoot.duration_seconds')" :value="$duration"
            :hint="__('packs.pentahoot.duration_hint')" required min="5" max="300" :data-range-message="__('packs.pentahoot.duration_hint')" x-model.number="duration" />
    </div>
</fieldset>
