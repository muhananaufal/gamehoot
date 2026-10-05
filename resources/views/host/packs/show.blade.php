@php
    use App\Enums\GameType;
    use Illuminate\Support\Js;

    $breadcrumbs = $pack
        ? [[__('packs.title'), route('host.packs.index')], [$pack->title, null]]
        : [[__('packs.title'), null]];
    $formOpen = $creating || $editing;
    $orderIds = $questions?->pluck('id')->all() ?? [];
    $createFailed = old('form') === 'create-pack' && $errors->any();
    // U14: moving around the editor swaps only the pack panel and the list, without reloading.
    $swap = '#pack-panel, #pack-list';
    $confirmTexts = [
        'delete' => ['title' => __('packs.delete_pack') . '?', 'body' => __('packs.delete_pack_confirm'), 'label' => __('packs.delete_pack'), 'method' => 'DELETE', 'tone' => 'destructive'],
        'deleteQuestion' => ['title' => __('packs.delete_question_title', ['name' => '__N__']), 'body' => __('packs.delete_question_confirm'), 'label' => __('packs.delete_question'), 'method' => 'DELETE', 'tone' => 'destructive'],
    ];
@endphp
<x-layouts.host :title="$pack ? $pack->title : __('packs.title')" :breadcrumbs="$breadcrumbs">
    {{-- U2: New pack opens a short form; inside a pack, Add question is the main action. --}}
    <x-slot:actions>
        <x-confirm-dialog :title="__('packs.new_pack')" :trigger="__('packs.new_pack')" trigger-icon="plus" :trigger-variant="$pack ? 'secondary' : 'primary'" :open="$createFailed">
            <form method="POST" action="{{ route('host.packs.store') }}" class="flex flex-col gap-3" novalidate>
                @csrf
                <input type="hidden" name="form" value="create-pack">
                <x-field name="title" :label="__('packs.pack_title')" id="new-pack-title" :submitted="old('form') === 'create-pack'" maxlength="100" autofocus />
                <fieldset class="flex flex-col gap-1.5">
                    <legend class="mb-1.5 text-sm font-semibold">{{ __('packs.game_type') }}</legend>
                    @foreach ($types as $type)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-[15px]">
                            <input type="radio" name="game_type" value="{{ $type->value }}" class="size-5" @checked(old('game_type', GameType::Pentahoot->value) === $type->value)>
                            {{ __('games.types.' . $type->value) }}
                        </label>
                    @endforeach
                    @error('game_type')
                        <p class="text-sm font-semibold text-on-danger">{{ $message }}</p>
                    @enderror
                </fieldset>
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
            {{-- Pack list, grouped by game type. U14: searchable, with its own scroll, kept in view. --}}
            <nav id="pack-list" x-data="{ q: '' }" aria-label="{{ __('packs.title') }}"
                class="flex flex-col gap-4 self-start rounded-card bg-surface p-4 lg:sticky lg:top-20 lg:max-h-[calc(100dvh-6rem)] lg:overflow-y-auto">
                <label class="relative flex items-center">
                    <x-icon name="search" class="pointer-events-none absolute left-3 text-muted" />
                    <input type="search" x-model="q" aria-label="{{ __('packs.search') }}" placeholder="{{ __('packs.search') }}" autocomplete="off"
                        class="h-11 w-full rounded-control border border-line-strong bg-field pr-3 pl-10 text-[15px] text-ink">
                </label>
                @foreach ($types as $groupType)
                    @continue(! $packs->has($groupType->value))
                    @php $group = $packs->get($groupType->value); @endphp
                    <div class="flex flex-col gap-1">
                        <h2 class="px-2 text-[11px] font-bold tracking-[0.08em] text-muted uppercase">{{ __('games.types.' . $groupType->value) }}</h2>
                        @foreach ($group as $item)
                            {{-- Another pack changes the page title and breadcrumb too, so the whole content moves. --}}
                            <a href="{{ route('host.packs.show', $item) }}" @if ($pack?->is($item)) aria-current="page" @endif
                                x-show="@js(mb_strtolower($item->title)).includes(q.trim().toLowerCase())"
                                @class([
                                    'flex min-h-11 items-center justify-between gap-2 rounded-control px-3 text-[15px]',
                                    'bg-subtle font-bold' => $pack?->is($item),
                                    'hover:bg-subtle' => ! $pack?->is($item),
                                ])>
                                <span class="truncate">{{ $item->title }}</span>
                                <span class="text-sm text-muted tabular-nums">{{ $item->questions_count }}</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>

            {{-- U14: the part that changes while working on a pack: the pack and, beside it, the question form. --}}
            <div id="pack-panel" @class(['grid min-w-0 gap-5 self-start', 'xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)]' => $pack && $formOpen])>
                @if (! $pack)
                    <x-empty-state :title="__('packs.pick_title')" :body="__('packs.pick_pack')" />
                @else
                    <section class="flex min-w-0 flex-col gap-4 rounded-card bg-surface p-6"
                        x-data x-init="$store.confirm.texts = @js($confirmTexts)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex flex-col">
                                <span class="text-xs font-bold tracking-[0.08em] text-muted uppercase">{{ __('packs.pack_label', ['type' => __('games.types.' . $pack->game_type->value)]) }}</span>
                                {{-- U6: when the pack last changed, in WIB (T10). --}}
                                <span class="text-[13px] text-muted">{{ __('packs.changed', ['time' => $pack->updated_at?->timezone(config('app.display_timezone'))->format('j M, H:i')]) }}</span>
                            </div>
                            {{-- U2: destructive actions never sit in the page header. U5: the pack has one, so it is an icon button, not a menu. --}}
                            <button type="button" class="row-action" aria-label="{{ __('packs.delete_pack_named', ['title' => $pack->title]) }}" title="{{ __('packs.delete_pack') }}"
                                @click="$store.confirm.askFor('delete', {{ Js::from($pack->title) }}, {{ Js::from(route('host.packs.destroy', $pack)) }})"><i data-icon="trash"></i></button>
                        </div>

                        {{-- Renaming changes the page title and breadcrumb: the whole content moves. --}}
                        <form method="POST" action="{{ route('host.packs.update', $pack) }}" class="flex flex-wrap items-end gap-2.5" novalidate>
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="form" value="rename-pack">
                            <div class="grow">
                                <x-field name="title" :label="__('packs.pack_title')" :value="$pack->title" id="rename-pack" :submitted="old('form') === 'rename-pack'" maxlength="100" />
                            </div>
                            <x-button type="submit" variant="secondary">{{ __('packs.rename') }}</x-button>
                        </form>

                        <p class="rounded-control bg-subtle px-4 py-3 text-sm text-on-subtle">{{ __('packs.copy_note') }}</p>

                        @if ($questions->isEmpty())
                            <x-empty-state :title="__('packs.no_questions')" :body="__('packs.no_questions_hint')" class="bg-subtle py-8">
                                <x-button :href="route('host.packs.questions.create', $pack)" :up-target="$swap" up-history="true" variant="secondary"><x-icon name="plus" />{{ __('packs.add_question') }}</x-button>
                            </x-empty-state>
                        @else
                            {{-- U9: drag a question by its handle; the arrows stay for keyboards and screen readers. --}}
                            <div x-data="sortableList">
                                <form method="POST" action="{{ route('host.packs.questions.reorder', $pack) }}" up-target="{{ $swap }}" up-history="true" x-ref="orderForm" class="hidden">
                                    @csrf
                                </form>
                                <ol class="flex flex-col gap-2">
                                    @foreach ($questions as $question)
                                        @php
                                            $number = $loop->iteration;
                                            $up = $orderIds;
                                            $down = $orderIds;
                                            if (! $loop->first) { [$up[$loop->index - 1], $up[$loop->index]] = [$up[$loop->index], $up[$loop->index - 1]]; }
                                            if (! $loop->last) { [$down[$loop->index + 1], $down[$loop->index]] = [$down[$loop->index], $down[$loop->index + 1]]; }
                                        @endphp
                                        <li data-id="{{ $question->id }}" draggable="true"
                                            @dragstart="start($event, {{ $loop->index }})" @dragenter.prevent="enter({{ $loop->index }})" @dragover.prevent
                                            @drop.prevent="drop({{ $loop->index }})" @dragend="end()"
                                            :class="{ 'opacity-50': from === {{ $loop->index }}, 'border-accent bg-subtle': over === {{ $loop->index }} && from !== {{ $loop->index }} }"
                                            @class(['flex items-center gap-2 rounded-control border px-2 py-2 transition-colors', 'border-accent' => $editing?->is($question), 'border-line-soft' => ! $editing?->is($question)])>
                                            <span class="flex h-11 w-6 shrink-0 cursor-grab items-center justify-center text-muted active:cursor-grabbing" title="{{ __('packs.drag') }}" aria-hidden="true">
                                                <x-icon name="grip" />
                                            </span>
                                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-subtle text-sm font-bold">{{ $number }}</span>
                                            <div class="flex min-w-0 grow flex-col">
                                                @include('host.packs.summary.' . $pack->game_type->value, ['question' => $question])
                                            </div>
                                            <div class="flex shrink-0 items-center">
                                                @foreach ([['up', $up, ! $loop->first], ['down', $down, ! $loop->last]] as [$direction, $order, $enabled])
                                                    <form method="POST" action="{{ route('host.packs.questions.reorder', $pack) }}" up-target="{{ $swap }}" up-history="true">
                                                        @csrf
                                                        @foreach ($order as $id)
                                                            <input type="hidden" name="order[]" value="{{ $id }}">
                                                        @endforeach
                                                        <button type="submit" @disabled(! $enabled) aria-label="{{ __('packs.move_' . $direction, ['number' => $number]) }}"
                                                            class="flex size-11 items-center justify-center rounded-control text-muted hover:bg-subtle disabled:opacity-30">
                                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                                <path d="{{ $direction === 'up' ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6' }}"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
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
