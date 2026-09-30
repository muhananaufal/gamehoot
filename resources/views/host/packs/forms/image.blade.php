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
    <input id="{{ $pickerId }}" type="file" accept="image/jpeg,image/png,image/heic,.heic,.heif" @change="pick($event)"
        aria-describedby="{{ $pickerId }}-hint{{ $error ? " {$pickerId}-error" : '' }}"
        class="min-h-11 text-sm file:mr-3 file:h-11 file:cursor-pointer file:rounded-control file:border file:border-line-strong file:bg-surface file:px-4 file:font-semibold file:text-ink">
    <input type="file" name="{{ $field }}" x-ref="large" class="hidden" tabindex="-1" aria-hidden="true">
    <input type="file" name="{{ $field }}_small" x-ref="small" class="hidden" tabindex="-1" aria-hidden="true">
    <p x-show="busy" class="text-sm text-muted" role="status">{{ __('packs.gambar.preparing') }}</p>
    <p x-show="error" x-text="error" role="alert" class="text-sm font-semibold text-on-danger"></p>
    @if ($error)
        <p id="{{ $pickerId }}-error" class="text-sm font-semibold text-on-danger">{{ $error }}</p>
    @endif
</div>
