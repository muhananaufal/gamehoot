@props([
    'name' => 'screen_theme',
    'value',
    'themes',
    'hint' => null,
])
@php $current = old($name, $value); @endphp
<fieldset class="flex flex-col gap-1.5">
    <legend class="mb-1.5 text-body-s font-semibold">{{ __('events.screen_theme') }}</legend>
    <div class="inline-flex w-fit gap-1 rounded-control border border-field-line bg-field p-1">
        @foreach ($themes as $theme)
            <label class="cursor-pointer">
                <input type="radio" name="{{ $name }}" value="{{ $theme->value }}" class="peer sr-only" @checked($current === $theme->value)>
                <span class="flex h-11 items-center rounded-[8px] px-4 text-body-s font-semibold text-muted peer-checked:bg-inverse peer-checked:text-on-inverse peer-focus-visible:outline-3 peer-focus-visible:outline-accent">
                    {{ __('events.themes.' . $theme->value) }}
                </span>
            </label>
        @endforeach
    </div>
    @if ($hint)
        <p class="text-body-s text-muted">{{ $hint }}</p>
    @endif
</fieldset>
