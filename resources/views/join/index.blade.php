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

            {{-- A3: one form for the whole list and the button styles on the list, so a list of 500
                 names stays small when hundreds of phones open it at once. --}}
            <form method="POST" action="{{ route('join.claim', $event) }}" aria-label="{{ __('join.pick_title') }}">
                @csrf
                <ul class="flex flex-col gap-2 [&_button]:flex [&_button]:min-h-14 [&_button]:w-full [&_button]:items-center [&_button]:rounded-control [&_button]:border [&_button]:border-line-strong [&_button]:bg-surface [&_button]:px-4 [&_button]:text-left [&_button]:text-lg [&_button]:font-semibold [&_button:hover]:border-accent">
                    @foreach ($names as $person)
                        <li x-show="shows({{ $loop->index }})"><button type="submit" name="person" value="{{ $person->id }}">{{ $person->name }}</button></li>
                    @endforeach
                </ul>
            </form>
            <p class="text-center text-sm text-muted" x-show="noMatch" x-cloak>{{ __('join.no_match') }}</p>
        @endif

        <p class="text-center text-sm text-muted">{{ __('join.wrong_name') }}</p>
    </div>
</x-layouts.phone>
