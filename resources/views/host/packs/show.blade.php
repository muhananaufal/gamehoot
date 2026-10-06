@php
    use App\Enums\GameType;
    use Illuminate\Support\Js;

    $breadcrumbs = $pack
        ? [[__('packs.title'), route('host.packs.index')], [$pack->title, null]]
        : [[__('packs.title'), null]];
    $formOpen = $creating || $editing;
    $createFailed = old('form') === 'create-pack' && $errors->any();
    // U14: working on questions swaps only the pack panel and this pack's question count in the
    // list; the list itself is never sent again (measured: 1,000 packs made the page 668 KB).
    $swap = '#pack-panel' . ($pack ? ', #pack-count-' . $pack->id : '');
    $confirmTexts = [
        'delete' => ['title' => __('packs.delete_pack') . '?', 'body' => __('packs.delete_pack_confirm'), 'label' => __('packs.delete_pack'), 'method' => 'DELETE', 'tone' => 'destructive'],
        'rename' => ['title' => __('packs.rename_pack'), 'label' => __('packs.rename'), 'method' => 'PUT', 'tone' => 'primary',
            'field' => ['name' => 'title', 'label' => __('packs.pack_title'), 'maxlength' => 100, 'form' => 'rename-pack']],
        'deleteQuestion' => ['title' => __('packs.delete_question_title', ['name' => '__N__']), 'body' => __('packs.delete_question_confirm'), 'label' => __('packs.delete_question'), 'method' => 'DELETE', 'tone' => 'destructive'],
    ];
    // U14: Rename and Delete sit in the pack card's menu.
    $packMenu = $pack ? [
        ['key' => 'rename', 'label' => __('packs.rename'), 'type' => 'confirm', 'url' => route('host.packs.update', '__P__')],
        ['key' => 'delete', 'label' => __('packs.delete_pack'), 'type' => 'confirm', 'danger' => true, 'url' => route('host.packs.destroy', '__P__')],
    ] : [];
    $renameRetry = $pack && old('form') === 'rename-pack' && $errors->has('title')
        ? ['name' => $pack->title, 'url' => route('host.packs.update', $pack), 'id' => $pack->id, 'previous' => ['value' => old('title'), 'error' => $errors->first('title')]]
        : null;
@endphp
<x-layouts.host :title="$pack ? $pack->title : __('packs.title')" :breadcrumbs="$breadcrumbs">
    {{-- U2: New pack opens a short form; inside a pack, Add question is the main action. --}}
    <x-slot:actions>
        <x-confirm-dialog :title="__('packs.new_pack')" :trigger="__('packs.new_pack')" trigger-icon="plus" :trigger-variant="$pack ? 'secondary' : 'primary'" :open="$createFailed">
            <form method="POST" action="{{ route('host.packs.store') }}" class="flex flex-col gap-3" novalidate>
                @csrf
                <input type="hidden" name="form" value="create-pack">
                <x-field name="title" :label="__('packs.pack_title')" id="new-pack-title" :submitted="old('form') === 'create-pack'" required maxlength="100" autofocus />
                <div class="flex flex-col gap-1.5">
                    <span class="text-sm font-semibold">{{ __('packs.game_type') }}</span>
                    <x-select name="game_type" :options="array_map(fn (GameType $type): array => ['value' => $type->value, 'label' => __('games.types.' . $type->value)], $types)"
                        :value="old('game_type', GameType::Pentahoot->value)" :label="__('packs.game_type')" class="w-full" />
                </div>
                <div class="flex justify-end gap-2.5 pt-1.5">
                    <x-button variant="secondary" type="button" @click="$el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                    <x-button type="submit">{{ __('packs.create') }}</x-button>
                </div>
            </form>
        </x-confirm-dialog>
        @if ($pack)
            <x-button :href="route('host.packs.questions.create', $pack)" :up-target="$swap" up-history="true"><x-icon name="plus" />{{ __('packs.add_question') }}</x-button>
        @endif
    </x-slot:actions>

    @if ($packs->isEmpty())
        <x-empty-state :title="__('packs.empty_title')" :body="__('packs.empty')" />
    @else
        <div class="grid gap-5 lg:grid-cols-[260px_minmax(0,1fr)]">
            {{-- U14: the pack list; another pack swaps the panel (resources/js/navigation.js). --}}
            <x-pack-list :packs="$packs" :types="$types" :current="$pack" />

            {{-- U14: the part that changes while working on a pack: the pack and, beside it, the question form. --}}
            <div id="pack-panel" @class(['grid min-w-0 gap-5 self-start', 'xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)]' => $pack && $formOpen])>
                @if (! $pack)
                    <x-empty-state :title="__('packs.pick_title')" :body="__('packs.pick_pack')" />
                @else
                    <section class="flex min-w-0 flex-col gap-4 rounded-card bg-surface p-6"
                        x-data x-init="$store.confirm.texts = @js($confirmTexts); $store.rowMenu.spec = @js($packMenu); const retry = document.getElementById('rename-retry'); if (retry) { const r = JSON.parse(retry.textContent); $store.confirm.askFor('rename', r.name, r.url, r.id, r.previous) }">
                        @if ($renameRetry)
                            <script type="application/json" id="rename-retry">@json($renameRetry)</script>
                        @endif
                        {{-- U14: the pack's title heads the card, its game type and last change below; Rename and Delete in the menu. --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 flex-col gap-0.5">
                                <h2 class="font-display text-2xl font-bold break-words">{{ $pack->title }}</h2>
                                {{-- U6: when the pack last changed, in WIB (T10). --}}
                                <span class="text-sm text-muted">{{ __('games.types.' . $pack->game_type->value) }} · {{ __('packs.changed', ['time' => $pack->updated_at?->timezone(config('app.display_timezone'))->format('j M, H:i')]) }}</span>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="text-sm text-muted tabular-nums">{{ trans_choice('packs.question_count', $questions->count()) }}</span>
                                <button type="button" class="row-action" data-menu data-id="{{ $pack->id }}" data-name="{{ $pack->title }}" data-rename="1" data-delete="1"
                                    @click="$store.rowMenu.openFrom($el)" aria-haspopup="menu" aria-label="{{ __('packs.pack_actions', ['title' => $pack->title]) }}" title="{{ __('ui.actions') }}"><i data-icon="more-vertical"></i></button>
                            </div>
                        </div>

                        <p class="rounded-control bg-subtle px-4 py-3 text-sm text-on-subtle">{{ __('packs.copy_note') }}</p>

                        @if ($questions->isEmpty())
                            <x-empty-state :title="__('packs.no_questions')" :body="__('packs.no_questions_hint')" class="bg-subtle py-8">
                                <x-button :href="route('host.packs.questions.create', $pack)" :up-target="$swap" up-history="true" variant="secondary"><x-icon name="plus" />{{ __('packs.add_question') }}</x-button>
                            </x-empty-state>
                        @else
                            {{-- U9: drag a question by its handle; the arrows stay for keyboards and screen readers. --}}
                            <div x-data="sortableList" data-save-failed="{{ __('packs.order_failed') }}">
                                <form method="POST" action="{{ route('host.packs.questions.reorder', $pack) }}" up-target="{{ $swap }}" up-history="true" x-ref="orderForm" class="hidden">
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
                                            <span data-number class="flex size-8 shrink-0 items-center justify-center rounded-full bg-subtle text-sm font-bold">{{ $number }}</span>
                                            <div class="flex min-w-0 grow flex-col">
                                                @include('host.packs.summary.' . $pack->game_type->value, ['question' => $question])
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
                                                <a href="{{ route('host.packs.questions.edit', [$pack, $question]) }}" up-target="{{ $swap }}" up-history="true"
                                                    aria-label="{{ __('packs.edit_question', ['number' => $number]) }}" title="{{ __('packs.edit_question', ['number' => $number]) }}"
                                                    class="flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle hover:text-ink">
                                                    <x-icon name="pencil" />
                                                </a>
                                                <button type="button" aria-label="{{ __('packs.delete_question_numbered', ['number' => $number]) }}" title="{{ __('packs.delete_question_numbered', ['number' => $number]) }}"
                                                    @click="$store.confirm.askFor('deleteQuestion', {{ Js::from(__('packs.question_number', ['number' => $number])) }}, {{ Js::from(route('host.packs.questions.destroy', [$pack, $question])) }})"
                                                    class="flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle hover:text-on-danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </section>

                    {{-- Question form --}}
                    @if ($formOpen)
                        @php
                            $number = $editing ? $questions->search(fn ($q) => $q->is($editing)) + 1 : null;
                            // E8: only forms with images are sent as multipart.
                            $multipart = app(\App\Games\GameEngines::class)->for($pack->game_type)->imageFields() !== [];
                        @endphp
                        <section class="flex flex-col gap-4 self-start rounded-card bg-surface p-6 xl:sticky xl:top-20" aria-labelledby="question-form-heading">
                            <h2 id="question-form-heading" class="font-display text-xl font-semibold">
                                {{ $editing ? __('packs.edit_question', ['number' => $number]) : __('packs.new_question') }}
                            </h2>
                            <form method="POST" action="{{ $editing ? route('host.packs.questions.update', [$pack, $editing]) : route('host.packs.questions.store', $pack) }}"
                                up-target="{{ $swap }}" up-history="true" @if ($multipart) enctype="multipart/form-data" @endif class="flex flex-col gap-4" novalidate>
                                @csrf
                                @if ($editing)
                                    @method('PUT')
                                @endif
                                @include('host.packs.forms.' . $pack->game_type->value, ['question' => $editing])
                                <div class="flex justify-end gap-2.5 pt-2">
                                    <x-button variant="secondary" :href="route('host.packs.show', $pack)" :up-target="$swap" up-history="true">{{ __('ui.cancel') }}</x-button>
                                    <x-button type="submit">{{ __('packs.save_question') }}</x-button>
                                </div>
                            </form>
                        </section>
                    @endif
                @endif
            </div>
        </div>
    @endif
</x-layouts.host>
