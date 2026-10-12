{{-- U4: shown while a search, filter or date range narrows a list kept in localStorage, so a
     list never looks empty without a visible reason. --}}
<button type="button" x-show="active" x-cloak @click="reset()"
    {{ $attributes->merge(['class' => 'inline-flex h-11 items-center gap-1.5 rounded-control px-3 text-body-s font-semibold text-link hover:bg-subtle']) }}>
    <x-icon name="close" class="size-4" />{{ __('ui.reset_filters') }}
</button>
