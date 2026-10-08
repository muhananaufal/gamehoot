{{-- U14, D-2, D-9: the question form beside the list, the same for a pack and a game. The caller
     gives $action, $cancelUrl, $swap, $type, the question being edited ($editing, null for a new
     one) and its $number; $multipart for the types with images (E8). --}}
<section class="flex flex-col gap-4 self-start rounded-card bg-surface p-6 xl:sticky xl:top-20" aria-labelledby="question-form-heading">
    <h2 id="question-form-heading" class="font-display text-xl font-semibold">
        {{ $editing ? __('packs.edit_question', ['number' => $number]) : __('packs.new_question') }}
    </h2>
    <form method="POST" action="{{ $action }}"
        up-target="{{ $swap }}" up-history="true" @if ($multipart) enctype="multipart/form-data" @endif class="flex flex-col gap-4" novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif
        @include('host.packs.forms.' . $type->value, ['question' => $editing])
        <div class="flex justify-end gap-2.5 pt-2">
            <x-button variant="secondary" :href="$cancelUrl" :up-target="$swap" up-history="true">{{ __('ui.cancel') }}</x-button>
            <x-button type="submit">{{ __('packs.save_question') }}</x-button>
        </div>
    </form>
</section>
