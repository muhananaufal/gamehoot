{{-- U9, U14, D-9: the questions of a pack or of a game that has not started: drag by the handle or
     move with the arrows (saved once after the last click), edit opens the form beside the list,
     delete asks first. The caller gives the routes: $reorderUrl, $editUrl($question),
     $deleteUrl($question), the Unpoly target $swap and the game type $type. --}}
@php use Illuminate\Support\Js; @endphp
{{-- U9: drag a question by its handle; the arrows stay for keyboards and screen readers. --}}
<div x-data="sortableList" data-save-failed="{{ __('packs.order_failed') }}" data-reload="{{ $swap }}">
    <form method="POST" action="{{ $reorderUrl }}" up-target="{{ $swap }}" up-history="true" x-ref="orderForm" class="hidden">
        @csrf
    </form>
    {{-- U8: rows slide to their new place (x-flip). --}}
    <ol class="flex flex-col gap-2" x-flip.moves>
        @foreach ($questions as $question)
            @php $number = $loop->iteration; @endphp
            <li data-id="{{ $question->id }}" draggable="true"
                @dragstart="start($event, {{ $loop->index }})" @dragenter.prevent="enter({{ $loop->index }})" @dragover.prevent
                @drop.prevent="drop({{ $loop->index }})" @dragend="end()"
                :class="{ 'opacity-50': from === {{ $loop->index }}, 'border-accent bg-subtle': over === {{ $loop->index }} && from !== {{ $loop->index }} }"
                @class(['flex items-center gap-2 rounded-control border px-2 py-2 transition-colors', 'border-accent' => $editing?->is($question), 'border-line-soft' => ! $editing?->is($question)])>
                <span class="flex h-11 w-6 shrink-0 cursor-grab items-center justify-center text-muted active:cursor-grabbing" title="{{ __('packs.drag') }}" aria-hidden="true">
                    <x-icon name="grip" />
                </span>
                <span data-number class="flex size-8 shrink-0 items-center justify-center rounded-full bg-subtle text-body-s font-bold">{{ $number }}</span>
                <div class="flex min-w-0 grow flex-col">
                    @include('host.packs.summary.' . $type->value, ['question' => $question])
                </div>
                <div class="flex shrink-0 items-center">
                    {{-- U14: the arrows move the row at once; the order is saved once after the last click. --}}
                    @foreach ([['up', -1, ! $loop->first], ['down', 1, ! $loop->last]] as [$direction, $delta, $enabled])
                        <button type="button" data-move="{{ $direction }}" @click="nudge($el, {{ $delta }})" @disabled(! $enabled) aria-label="{{ __('packs.move_' . $direction, ['number' => $number]) }}"
                            class="flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle disabled:opacity-30">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="{{ $direction === 'up' ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}"></path>
                            </svg>
                        </button>
                    @endforeach
                    {{-- U14: edit and delete are icon buttons on the row; delete asks first (U5). --}}
                    <a href="{{ $editUrl($question) }}" up-target="{{ $swap }}" up-history="true"
                        aria-label="{{ __('packs.edit_question', ['number' => $number]) }}" title="{{ __('packs.edit_question', ['number' => $number]) }}"
                        class="flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle hover:text-ink">
                        <x-icon name="pencil" />
                    </a>
                    <button type="button" aria-label="{{ __('packs.delete_question_numbered', ['number' => $number]) }}" title="{{ __('packs.delete_question_numbered', ['number' => $number]) }}"
                        @click="$store.confirm.askFor('deleteQuestion', {{ Js::from(__('packs.question_number', ['number' => $number])) }}, {{ Js::from($deleteUrl($question)) }})"
                        class="flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle hover:text-on-danger">
                        <x-icon name="trash" />
                    </button>
                </div>
            </li>
        @endforeach
    </ol>
</div>
