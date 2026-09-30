@props([
    // list of ['label' => string, 'hint' => ?string, 'state' => done|current|todo|surrendered|skipped]
    // Live lists (Live control) may give 'stateExpr', a JavaScript expression returning the
    // state, and 'click' / 'clickable' expressions to make the label a button.
    'steps',
])
@php
    $classes = [
        'done' => 'bg-success-dot text-on-accent',
        'current' => 'bg-accent text-on-accent',
        'todo' => 'border-2 border-line-strong bg-surface text-muted',
        'surrendered' => 'bg-subtle text-muted',
        'skipped' => 'border-2 border-dashed border-line-strong bg-surface text-muted',
    ];
@endphp
{{-- F20: the one progress timeline, always vertical. --}}
<ol {{ $attributes->merge(['class' => 'flex flex-col']) }}>
    @foreach ($steps as $step)
        @php
            $state = $step['state'] ?? 'todo';
            $live = $step['stateExpr'] ?? null;
        @endphp
        <li class="relative flex gap-3.5 pb-6 last:pb-0"
            @if ($live) :aria-current="({{ $live }}) === 'current' ? 'step' : null" @elseif ($state === 'current') aria-current="step" @endif>
            @unless ($loop->last)
                <span class="absolute top-9 bottom-1 left-[15px] w-0.5 bg-line-strong" aria-hidden="true"></span>
            @endunless
            @if ($live)
                <span class="relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                    :class="@js($classes)[{{ $live }}]" aria-hidden="true">
                    <svg x-show="({{ $live }}) === 'done'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"></path></svg>
                    <span x-show="({{ $live }}) !== 'done'">{{ $loop->iteration }}</span>
                </span>
            @else
                <span class="relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $classes[$state] }}" aria-hidden="true">
                    @if ($state === 'done')
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"></path></svg>
                    @elseif ($state === 'surrendered')
                        –
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
            @endif
            <span class="flex min-w-0 flex-col pt-1">
                @if ($step['click'] ?? null)
                    <button type="button" class="min-h-11 -mt-2.5 text-left font-bold enabled:hover:text-accent disabled:cursor-default"
                        @click="{{ $step['click'] }}" :disabled="!({{ $step['clickable'] ?? 'true' }})">{{ $step['label'] }}</button>
                @else
                    <span @class(['font-bold', 'text-muted' => $state === 'todo' && ! $live])>{{ $step['label'] }}</span>
                @endif
                @if ($step['hint'] ?? null)
                    <span class="text-sm text-muted" @if ($step['hintExpr'] ?? null) x-text="{{ $step['hintExpr'] }}" @endif>{{ $step['hint'] }}</span>
                @endif
            </span>
        </li>
    @endforeach
</ol>
