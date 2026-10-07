@php
    use App\Enums\GameStatus;
    use Illuminate\Support\Js;

    // D-9: what changes while working on a game: the game and the question form beside it.
    $swap = '#game-panel';
    $formOpen = $editing !== null || $creating;
    $running = $game && $game->id === $event->active_game_id && $game->status !== GameStatus::Finished;
    // D-9: Reload brings the pack back when it changed or the game was edited here, and the pack is still there.
    $canReload = $game && $game->source_pack_id && ! $game->packDeleted() && ($game->packChanged() || $game->edited_at);
    // U5, D-9: the game card menu (Rename, Reload, Delete) and its confirmations, sent once.
    $confirmTexts = $game ? [
        'rename' => ['title' => __('games.rename_title'), 'label' => __('games.rename'), 'method' => 'PUT', 'tone' => 'primary',
            'url' => route('host.events.games.update', [$event, '__P__']),
            'field' => ['name' => 'title', 'label' => __('games.game_title_label'), 'maxlength' => 100, 'form' => 'rename-game']],
        'reload' => ['title' => __('games.reload') . '?', 'label' => __('games.reload'), 'method' => 'POST', 'tone' => 'primary',
            'body' => __('games.reload_confirm', ['title' => $game->title]) . ($game->edited_at ? ' ' . __('games.reload_edits_lost') : ''),
            'url' => route('host.events.games.reload', [$event, '__P__'])],
        'delete' => ['title' => __('games.delete') . '?', 'body' => __('games.delete_confirm', ['title' => $game->title]), 'label' => __('games.delete'), 'method' => 'DELETE', 'tone' => 'destructive',
            'url' => route('host.events.games.destroy', [$event, '__P__'])],
        'deleteQuestion' => ['title' => __('packs.delete_question_title', ['name' => '__N__']), 'body' => __('games.delete_question_confirm'), 'label' => __('packs.delete_question'), 'method' => 'DELETE', 'tone' => 'destructive'],
    ] : [];
    $gameMenu = $game ? array_values(array_filter([
        ['key' => 'rename', 'label' => __('games.rename'), 'type' => 'confirm', 'url' => route('host.events.games.update', [$event, '__P__'])],
        ! $canReload ? null : ['key' => 'reload', 'label' => __('games.reload'), 'type' => 'confirm', 'url' => route('host.events.games.reload', [$event, '__P__'])],
        ['key' => 'delete', 'label' => __('games.delete'), 'type' => 'confirm', 'danger' => true, 'url' => route('host.events.games.destroy', [$event, '__P__'])],
    ])) : [];
@endphp
{{-- D-9, D-2: the games of an event, laid out as the Question packs page. --}}
<x-layouts.host :title="($game ? $game->title . ' · ' : '') . __('games.title') . ' · ' . $event->name" :event="$event"
    :breadcrumbs="$game
        ? [[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('games.title'), route('host.events.games.index', $event)], [$game->title, null]]
        : [[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('games.title'), null]]">
    {{-- U2: Add games opens the pack list, several packs at once, in the order picked. --}}
    <x-slot:actions>
        <x-confirm-dialog :title="__('games.add_games')" :trigger="__('games.add_game')" trigger-icon="plus" :trigger-variant="$editable ? 'secondary' : 'primary'" size="md">
            @if ($packs->isEmpty())
                <p class="text-[15px] text-muted">{{ __('games.no_packs') }}</p>
                <div class="flex justify-end gap-2.5 pt-1.5">
                    <x-button :href="route('host.packs.index')" variant="secondary">{{ __('host.nav.question_packs') }}</x-button>
                </div>
            @else
                <div x-data="packPicker({ labels: {{ Js::from(['one' => __('games.add_one'), 'other' => __('games.add_many')]) }} })" class="flex flex-col gap-3">
                    <x-pack-list :packs="$packs" :types="$types" pick />
                    {{-- D-9: what is picked, in order; each can be taken out again. --}}
                    <div x-show="picked.length > 0" x-cloak class="flex flex-wrap items-center gap-2 border-t border-line-soft pt-3">
                        <span class="text-sm font-semibold text-muted">{{ __('games.selected') }}</span>
                        <template x-for="chip in chips()" :key="chip.id">
                            <span class="inline-flex items-center gap-1 rounded-full bg-subtle py-1 pr-1 pl-2.5 text-[13px] font-bold text-on-subtle">
                                <span x-text="chip.number + '. ' + chip.title"></span>
                                <button type="button" class="flex size-6 items-center justify-center rounded-full hover:bg-line" @click="pick(chip.id, chip.title)"
                                    :aria-label="{{ Js::from(__('games.unpick', ['title' => '__T__'])) }}.replace('__T__', chip.title)"><x-icon name="close" class="size-3.5" /></button>
                            </span>
                        </template>
                    </div>
                    <form method="POST" action="{{ route('host.events.games.store', $event) }}" class="flex justify-end gap-2.5 pt-1.5">
                        @csrf
                        <template x-for="id in picked" :key="id"><input type="hidden" name="pack_ids[]" :value="id"></template>
                        <x-button variant="secondary" type="button" @click="clear(); $el.closest('dialog').close()">{{ __('ui.cancel') }}</x-button>
                        <x-button type="submit" data-test="add-games" x-bind:disabled="picked.length === 0"><span x-text="addLabel()">{{ __('games.add_game') }}</span></x-button>
                    </form>
                </div>
            @endif
        </x-confirm-dialog>
        @if ($editable)
            {{-- D-2: a question for this event only; the pack does not change. --}}
            <x-button :href="route('host.events.games.questions.create', [$event, $game])" :up-target="$swap" up-history="true"><x-icon name="plus" />{{ __('games.add_question') }}</x-button>
        @endif
    </x-slot:actions>

    @error('action')
        <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
    @enderror
    @error('pack_ids.*')
        <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
    @enderror

    <x-realtime :event="$event" :audience="\App\Enums\Audience::Host" />
    <div class="grid grid-cols-[minmax(0,1fr)] gap-5 lg:grid-cols-[260px_minmax(0,1fr)]">
        {{-- U12: game status follows starts and finishes made from Live control or by a co-host. --}}
        <div id="games-live" x-data="liveRegion" class="min-w-0 self-start">
            @include('host.games.list')
        </div>

        <div id="game-panel" @class(['grid min-w-0 gap-5 self-start', 'xl:grid-cols-[minmax(0,1fr)_minmax(0,420px)]' => $formOpen])>
            @if (! $game)
                <x-empty-state :title="__('games.empty_title')" :body="__('games.empty')" />
            @else
                <section class="flex min-w-0 flex-col gap-4 rounded-card bg-surface p-6"
                    x-data x-init="$store.confirm.texts = @js($confirmTexts); $store.rowMenu.spec = @js($gameMenu)">
                    {{-- D-9: the title heads the card, type, questions and markers below; the menu while it can change. --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-col gap-1">
                            <h2 class="font-display text-2xl font-bold break-words">{{ $game->title }}</h2>
                            {{-- U6: when it was added, or when it started (from the activity log), in WIB (T10). --}}
                            <span class="text-sm text-muted">{{ __('games.types.' . $game->type->value) }} · {{ trans_choice('games.questions', $questions->count()) }} ·
                                @if (isset($startedAt[$game->id]))
                                    {{ __('games.started_at', ['time' => $startedAt[$game->id]->timezone(config('app.display_timezone'))->format('j M, H:i')]) }}
                                @else
                                    {{ __('games.added_at', ['date' => $game->created_at?->timezone(config('app.display_timezone'))->format('j M')]) }}
                                @endif
                            </span>
                            <div class="flex flex-wrap gap-1.5 empty:hidden">
                                {{-- D-9: only a game that can still be reloaded says its pack moved on. --}}
                                @if ($editable && $game->packChanged())
                                    <x-chip tone="accent">{{ __('games.pack_changed') }}</x-chip>
                                @endif
                                @if ($game->edited_at)
                                    <x-chip>{{ __('games.edited') }}</x-chip>
                                @endif
                                @if ($game->packDeleted())
                                    <x-chip>{{ __('games.pack_deleted') }}</x-chip>
                                @endif
                            </div>
                        </div>
                        @if ($editable)
                            <button type="button" class="row-action shrink-0" data-menu data-id="{{ $game->id }}" data-name="{{ $game->title }}" data-rename="1" @if ($canReload) data-reload="1" @endif data-delete="1"
                                @click="$store.rowMenu.openFrom($el)" aria-haspopup="menu" aria-label="{{ __('games.game_actions', ['title' => $game->title]) }}" title="{{ __('ui.actions') }}"><i data-icon="more-vertical"></i></button>
                        @else
                            <x-chip :tone="$running ? 'success' : 'warning'" :dot="$running">{{ __('games.status.' . ($running ? 'running' : 'finished')) }}</x-chip>
                        @endif
                    </div>

                    @if (! $editable)
                        {{-- D-2: a game that has started can only be looked at. --}}
                        <p class="rounded-control bg-subtle px-4 py-3 text-sm text-on-subtle">{{ __('games.started_locked') }}</p>
                    @endif

                    @if ($questions->isEmpty())
                        <x-empty-state :title="__('games.no_questions')" :body="__('games.no_questions_hint')" class="bg-subtle py-8" />
                    @elseif ($editable)
                        @include('host.partials.question-list', [
                            'type' => $game->type,
                            'reorderUrl' => route('host.events.games.questions.reorder', [$event, $game]),
                            'editUrl' => fn ($question) => route('host.events.games.questions.edit', [$event, $game, $question]),
                            'deleteUrl' => fn ($question) => route('host.events.games.questions.destroy', [$event, $game, $question]),
                        ])
                    @else
                        <ol class="flex flex-col gap-2">
                            @foreach ($questions as $question)
                                <li class="flex items-center gap-2 rounded-control border border-line-soft px-3 py-2">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-subtle text-sm font-bold">{{ $loop->iteration }}</span>
                                    <div class="flex min-w-0 grow flex-col">
                                        @include('host.packs.summary.' . $game->type->value, ['question' => $question])
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        <div class="flex justify-end">
                            @if ($running)
                                <x-button :href="route('host.events.live', $event)">{{ __('games.open_live') }}</x-button>
                            @else
                                <x-button :href="route('host.events.results', $event)" variant="secondary">{{ __('live.results') }}</x-button>
                            @endif
                        </div>
                    @endif
                </section>

                @if ($formOpen)
                    @php
                        $number = $editing ? $questions->search(fn ($q) => $q->is($editing)) + 1 : null;
                        // E8: only forms with images are sent as multipart.
                        $multipart = app(\App\Games\GameEngines::class)->for($game->type)->imageFields() !== [];
                    @endphp
                    @include('host.partials.question-form', [
                        'type' => $game->type,
                        'action' => $editing ? route('host.events.games.questions.update', [$event, $game, $editing]) : route('host.events.games.questions.store', [$event, $game]),
                        'cancelUrl' => route('host.events.games.show', [$event, $game]),
                    ])
                @endif
            @endif
        </div>
    </div>
</x-layouts.host>
