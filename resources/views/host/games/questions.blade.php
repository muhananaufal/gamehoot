{{-- D-2: the copied questions of a game, editable with the pack form until they have been on screen. --}}
<x-layouts.host :title="__('games.questions_title', ['title' => $game->title]) . ' · ' . $event->name" :event="$event"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [$event->name, null], [__('games.title'), route('host.events.games.index', $event)], [$game->title, null]]">

    @error('action')
        <div role="alert" class="rounded-card bg-danger px-4 py-3 text-sm font-semibold text-on-danger">{{ $message }}</div>
    @enderror

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,460px)]">
        <section class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-labelledby="copies-heading">
            <div class="flex flex-col gap-1">
                <h2 id="copies-heading" class="font-display text-xl font-semibold">{{ __('games.questions_title', ['title' => $game->title]) }}</h2>
                <p class="text-sm text-muted">{{ __('games.questions_help') }}</p>
            </div>
            <ol class="flex flex-col gap-2">
                @foreach ($questions as $question)
                    <li @class(['flex items-center gap-3 rounded-control border px-3 py-2', 'border-accent' => $editing?->is($question), 'border-line-soft' => ! $editing?->is($question)])>
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-subtle text-sm font-bold">{{ $loop->iteration }}</span>
                        <div class="flex min-w-0 grow flex-col">
                            @include('host.packs.summary.' . $game->type->value, ['question' => $question])
                        </div>
                        @if ($locked[$question->id])
                            <x-chip>{{ __('games.locked') }}</x-chip>
                        @else
                            <x-button variant="link" :href="route('host.events.games.questions.edit', [$event, $game, $question])">{{ __('ui.edit') }}</x-button>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>

        @if ($editing && ! $locked[$editing->id])
            <section class="flex flex-col gap-4 rounded-card bg-surface p-6" aria-labelledby="copy-form-heading">
                <h2 id="copy-form-heading" class="font-display text-xl font-semibold">
                    {{ __('games.edit_question', ['number' => $questions->search(fn ($question) => $question->is($editing)) + 1]) }}
                </h2>
                <form method="POST" action="{{ route('host.events.games.questions.update', [$event, $game, $editing]) }}" enctype="multipart/form-data" class="flex flex-col gap-5" novalidate>
                    @csrf
                    @method('PUT')
                    @include('host.packs.forms.' . $game->type->value, ['question' => $editing])
                    <div class="flex justify-end gap-2.5">
                        <x-button :href="route('host.events.games.questions.index', [$event, $game])" variant="secondary">{{ __('ui.cancel') }}</x-button>
                        <x-button type="submit">{{ __('games.save_question') }}</x-button>
                    </div>
                </form>
            </section>
        @endif
    </div>
</x-layouts.host>
