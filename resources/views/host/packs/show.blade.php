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
            'field' => ['name' => 'title', 'label' => __('packs.pack_title'), 'placeholder' => __('examples.pack_title'), 'maxlength' => 100, 'form' => 'rename-pack']],
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
                <x-field name="title" :placeholder="__('examples.pack_title')" :label="__('packs.pack_title')" id="new-pack-title" :submitted="old('form') === 'create-pack'" required maxlength="100" autofocus />
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
                            @include('host.partials.question-list', [
                                'type' => $pack->game_type,
                                'reorderUrl' => route('host.packs.questions.reorder', $pack),
                                'editUrl' => fn ($question) => route('host.packs.questions.edit', [$pack, $question]),
                                'deleteUrl' => fn ($question) => route('host.packs.questions.destroy', [$pack, $question]),
                            ])
                        @endif
                    </section>

                    {{-- Question form --}}
                    @if ($formOpen)
                        @php
                            $number = $editing ? $questions->search(fn ($q) => $q->is($editing)) + 1 : null;
                            // E8: only forms with images are sent as multipart.
                            $multipart = app(\App\Games\GameEngines::class)->for($pack->game_type)->imageFields() !== [];
                        @endphp
                        @include('host.partials.question-form', [
                            'type' => $pack->game_type,
                            'action' => $editing ? route('host.packs.questions.update', [$pack, $editing]) : route('host.packs.questions.store', $pack),
                            'cancelUrl' => route('host.packs.show', $pack),
                        ])
                    @endif
                @endif
            </div>
        </div>
    @endif
</x-layouts.host>
