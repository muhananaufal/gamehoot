<x-layouts.phone :title="$event->name" :event="$event">
    {{-- F9: the whole list is sent once and searched on the phone, without a request per keystroke. --}}
    <div x-data="{
        query: '',
        names: @js($names->map(fn ($p) => mb_strtolower($p->name))->values()),
        shows(index) { const q = this.query.trim().toLowerCase(); return q === '' || this.names[index].includes(q); },
        get noMatch() { return this.query.trim() !== '' && ! this.names.some((_, i) => this.shows(i)); },
    }" class="flex flex-col gap-4">
        <div class="flex flex-col items-center gap-2 text-center">
            <x-logo :size="64" :wordmark="false" outline="stroke-canvas" />
            <h1 class="font-display text-[26px] leading-tight font-bold">{{ __('join.pick_title') }}</h1>
        </div>

        @if ($names->isEmpty())
            <p class="rounded-card bg-surface px-4 py-6 text-center text-muted">{{ __('join.no_names') }}</p>
        @else
            <div class="flex flex-col gap-1.5">
                <label for="name-search" class="text-sm font-semibold">{{ __('join.search') }}</label>
                <input id="name-search" type="search" x-model="query" autocomplete="off" autocapitalize="words"
                    class="h-12 rounded-control border border-line-strong bg-field px-4 text-base text-ink">
            </div>
            <p class="text-sm text-muted">{{ __('join.claimed_hidden') }}</p>

            <ul class="flex flex-col gap-2">
                @foreach ($names as $person)
                    <li x-show="shows({{ $loop->index }})">
                        <form method="POST" action="{{ route('join.claim', $event) }}">
                            @csrf
                            <input type="hidden" name="person" value="{{ $person->id }}">
                            <button type="submit" aria-label="{{ __('join.join_as', ['name' => $person->name]) }}"
                                class="flex min-h-14 w-full items-center rounded-card border-2 border-line bg-surface px-4 text-left text-lg font-semibold hover:border-accent">
                                {{ $person->name }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <p class="text-center text-sm text-muted" x-show="noMatch" x-cloak>{{ __('join.no_match') }}</p>
        @endif

        <p class="text-center text-sm text-muted">{{ __('join.wrong_name') }}</p>
    </div>
</x-layouts.phone>
