{{-- E16: points of a Tebak question, 0 to 2. --}}
<fieldset class="flex flex-col gap-1.5">
    <legend class="mb-1.5 text-body-s font-semibold">{{ __('packs.points') }}</legend>
    <div class="inline-flex w-fit gap-1 rounded-control border border-field-line bg-field p-1">
        @foreach (['0', '1', '2'] as $value)
            <label class="cursor-pointer">
                <input type="radio" name="points" value="{{ $value }}" class="peer sr-only" @checked($points === $value)>
                <span class="flex size-11 items-center justify-center rounded-[8px] text-body-s font-bold text-muted peer-checked:bg-inverse peer-checked:text-on-inverse peer-focus-visible:outline-3 peer-focus-visible:outline-accent">{{ $value }}</span>
            </label>
        @endforeach
    </div>
    <p class="text-body-s text-muted">{{ __('packs.points_hint') }}</p>
    @error('points')
        <p class="text-body-s font-semibold text-on-danger">{{ $message }}</p>
    @enderror
</fieldset>
