{{-- E8, E14: one image of a question. The picker has no name: the browser resizes the picked file and
     puts the 1920 px and 720 px results into the two named inputs. $current is the stored image
     (720 px) when editing; an empty picker keeps it. --}}
@php
    $error = $errors->first($field) ?: $errors->first($field . '_small');
    $pickerId = 'field-' . $field;
@endphp
<div x-data="imagePicker({
        current: @js($current),
        labels: @js([
            'heic' => __('games.gambar.heic'),
            'type' => __('games.gambar.not_image'),
            'too_big' => __('packs.gambar.too_big'),
            'unreadable' => __('packs.gambar.unreadable'),
        ]),
    })"
    class="flex flex-col gap-2">
    <label for="{{ $pickerId }}" class="text-sm font-semibold">{{ $label }}</label>
    <p id="{{ $pickerId }}-hint" class="text-sm text-muted">
        {{ $hint }} {{ __('packs.gambar.image_hint') }} @if ($current) {{ __('packs.gambar.replace_hint') }} @endif
    </p>
    <template x-if="preview">
        <img :src="preview" alt="{{ __('packs.gambar.preview', ['image' => mb_strtolower($label)]) }}"
            class="max-h-48 w-fit max-w-full rounded-control border border-line-soft object-contain" data-test="{{ $field }}-preview">
    </template>
    {{-- The browser field is cleared after resizing, so the button and the file name are drawn here. --}}
    <div class="flex flex-wrap items-center gap-3">
        <input id="{{ $pickerId }}" type="file" accept="image/jpeg,image/png,image/heic,.heic,.heif" @change="pick($event)"
            aria-describedby="{{ $pickerId }}-hint {{ $pickerId }}-file{{ $error ? " {$pickerId}-error" : '' }}"
            class="peer sr-only">
        <label for="{{ $pickerId }}" class="inline-flex h-11 cursor-pointer items-center rounded-control border border-line-strong bg-surface px-4 text-sm font-semibold text-ink peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent">{{ __('packs.gambar.pick') }}</label>
        <span id="{{ $pickerId }}-file" class="min-w-0 truncate text-sm text-muted"
            x-text="fileName || (preview ? @js(__('packs.gambar.kept')) : @js(__('packs.gambar.none')))"></span>
    </div>
    {{-- Browsers cannot refill a file field after a validation error. --}}
    @if ($errors->any() && ! $current)
        <p class="text-sm font-semibold text-muted">{{ __('packs.gambar.pick_again') }}</p>
    @endif
    <input type="file" name="{{ $field }}" x-ref="large" class="hidden" tabindex="-1" aria-hidden="true">
    <input type="file" name="{{ $field }}_small" x-ref="small" class="hidden" tabindex="-1" aria-hidden="true">
    <p x-show="busy" class="text-sm text-muted" role="status">{{ __('packs.gambar.preparing') }}</p>
    <p x-show="error" x-text="error" role="alert" class="text-sm font-semibold text-on-danger"></p>
    @if ($error)
        <p id="{{ $pickerId }}-error" class="text-sm font-semibold text-on-danger">{{ $error }}</p>
    @endif
</div>
