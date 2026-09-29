@php
    use App\Enums\GameType;

    $breadcrumbs = $pack
        ? [[__('packs.title'), route('host.packs.index')], [$pack->title, null]]
        : [[__('packs.title'), null]];
    $formOpen = $creating || $editing;
    $orderIds = $questions?->pluck('id')->all() ?? [];
@endphp
<x-layouts.host :title="$pack ? $pack->title : __('packs.title')" :breadcrumbs="$breadcrumbs">
    <div class="grid gap-5 lg:grid-cols-[260px_minmax(0,1fr)] {{ $formOpen ? 'xl:grid-cols-[260px_minmax(0,1fr)_minmax(0,420px)]' : '' }}">
        {{-- Pack list, grouped by game type --}}
        <aside class="flex flex-col gap-4 rounded-card bg-surface p-4" aria-label="{{ __('packs.title') }}">
            @if ($packs->isEmpty())
                <p class="px-2 text-sm text-muted">{{ __('packs.empty') }}</p>
            @endif
            @foreach ($types as $groupType)
                @continue(! $packs->has($groupType->value))
                @php $group = $packs->get($groupType->value); @endphp
                <div class="flex flex-col gap-1">
                    <h2 class="px-2 text-[11px] font-bold tracking-[0.08em] text-muted uppercase">{{ __('games.types.' . $groupType->value) }}</h2>
                    @foreach ($group as $item)
                        <a href="{{ route('host.packs.show', $item) }}" @if ($pack?->is($item)) aria-current="page" @endif
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

            <form method="POST" action="{{ route('host.packs.store') }}" class="flex flex-col gap-3 border-t border-line-soft pt-4" novalidate>
                @csrf
                <input type="hidden" name="form" value="create-pack">
                <h2 class="font-display text-lg font-semibold">{{ __('packs.new_pack') }}</h2>
                <x-field name="title" :label="__('packs.pack_title')" id="new-pack-title" :submitted="old('form') === 'create-pack'" maxlength="100" />
                <fieldset class="flex flex-col gap-1.5">
                    <legend class="mb-1.5 text-sm font-semibold">{{ __('packs.game_type') }}</legend>
                    @foreach ($types as $type)
                        <label class="flex min-h-11 items-center gap-2.5 text-[15px]">
                            <input type="radio" name="game_type" value="{{ $type->value }}" class="size-5" @checked(old('game_type', GameType::Pentahoot->value) === $type->value)>
                            {{ __('games.types.' . $type->value) }}
                        </label>
                    @endforeach
                    @error('game_type')
                        <p class="text-sm font-semibold text-on-danger">{{ $message }}</p>
                    @enderror
                </fieldset>
                <x-button type="submit" variant="secondary">{{ __('packs.create') }}</x-button>
            </form>
        </aside>

        {{-- Open pack --}}
        <section class="flex flex-col gap-4 rounded-card bg-surface p-6">
            @if (! $pack)
                <p class="text-muted">{{ __('packs.pick_pack') }}</p>
            @else
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex flex-col">
                        <span class="text-xs font-bold tracking-[0.08em] text-muted uppercase">{{ __('packs.pack_label', ['type' => __('games.types.' . $pack->game_type->value)]) }}</span>
                        <h1 class="font-display text-2xl font-bold">{{ $pack->title }}</h1>
                    </div>
                    <x-confirm-dialog :title="__('packs.delete_pack') . '?'" :trigger="__('packs.delete_pack')" trigger-variant="link-destructive">
                        <p class="text-[15px] text-muted">{{ __('packs.delete_pack_confirm') }}</p>
                        <form method="POST" action="{{ route('host.packs.destroy', $pack) }}" class="flex justify-end gap-2.5 pt-1.5">
                            @csrf
                            @method('DELETE')
                            <x-button variant="secondary" formmethod="dialog" type="submit">{{ __('ui.cancel') }}</x-button>
                            <x-button type="submit" variant="destructive">{{ __('packs.delete_pack') }}</x-button>
                        </form>
                    </x-confirm-dialog>
                </div>

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
                    <p class="text-muted">{{ __('packs.no_questions') }}</p>
                @else
                    <ol class="flex flex-col gap-2">
                        @foreach ($questions as $question)
                            @php
                                $number = $loop->iteration;
                                $up = $orderIds;
                                $down = $orderIds;
                                if (! $loop->first) { [$up[$loop->index - 1], $up[$loop->index]] = [$up[$loop->index], $up[$loop->index - 1]]; }
                                if (! $loop->last) { [$down[$loop->index + 1], $down[$loop->index]] = [$down[$loop->index], $down[$loop->index + 1]]; }
                            @endphp
                            <li @class(['flex items-center gap-3 rounded-control border px-3 py-2', 'border-accent' => $editing?->is($question), 'border-line-soft' => ! $editing?->is($question)])>
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-subtle text-sm font-bold">{{ $number }}</span>
                                <div class="flex min-w-0 grow flex-col">
                                    @include('host.packs.summary.' . $pack->game_type->value, ['question' => $question])
                                </div>
                                <div class="flex shrink-0 items-center">
                                    @foreach ([['up', $up, ! $loop->first], ['down', $down, ! $loop->last]] as [$direction, $order, $enabled])
                                        <form method="POST" action="{{ route('host.packs.questions.reorder', $pack) }}">
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
                                    <x-button variant="link" :href="route('host.packs.questions.edit', [$pack, $question])" class="ml-2">{{ __('ui.edit') }}</x-button>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif

                <x-button :href="route('host.packs.questions.create', $pack)" variant="secondary" class="self-start">{{ __('packs.add_question') }}</x-button>
            @endif
        </section>

        {{-- Question form --}}
        @if ($pack && $formOpen)
            @php
                $number = $editing ? $questions->search(fn ($q) => $q->is($editing)) + 1 : null;
            @endphp
            <section class="flex flex-col gap-4 rounded-card bg-surface p-6 lg:col-span-2 xl:col-span-1" aria-labelledby="question-form-heading">
                <h2 id="question-form-heading" class="font-display text-xl font-semibold">
                    {{ $editing ? __('packs.edit_question', ['number' => $number]) : __('packs.new_question') }}
                </h2>
                <form method="POST" action="{{ $editing ? route('host.packs.questions.update', [$pack, $editing]) : route('host.packs.questions.store', $pack) }}" class="flex flex-col gap-4" novalidate>
                    @csrf
                    @if ($editing)
                        @method('PUT')
                    @endif
                    @include('host.packs.forms.' . $pack->game_type->value, ['question' => $editing])
                    <div class="flex justify-end gap-2.5 pt-2">
                        <x-button variant="secondary" :href="route('host.packs.show', $pack)">{{ __('ui.cancel') }}</x-button>
                        <x-button type="submit">{{ __('packs.save_question') }}</x-button>
                    </div>
                </form>
                @if ($editing)
                    <form method="POST" action="{{ route('host.packs.questions.destroy', [$pack, $editing]) }}" class="border-t border-line-soft pt-4">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="link-destructive">{{ __('packs.delete_question') }}</x-button>
                    </form>
                @endif
            </section>
        @endif
    </div>
</x-layouts.host>
