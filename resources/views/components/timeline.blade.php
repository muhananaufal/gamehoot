@props([
    // list of ['label' => string, 'hint' => ?string, 'state' => done|current|todo|surrendered|skipped]
    'steps',
])
{{-- F20: the one progress timeline, always vertical. --}}
<ol {{ $attributes->merge(['class' => 'flex flex-col']) }}>
    @foreach ($steps as $step)
        @php $state = $step['state']; @endphp
        <li class="relative flex gap-3.5 pb-6 last:pb-0" @if ($state === 'current') aria-current="step" @endif>
            @unless ($loop->last)
                <span class="absolute top-9 bottom-1 left-[15px] w-0.5 bg-line-strong" aria-hidden="true"></span>
            @endunless
            <span @class([
                'relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                'bg-success-dot text-on-accent' => $state === 'done',
                'bg-accent text-on-accent' => $state === 'current',
                'border-2 border-line-strong bg-surface text-muted' => $state === 'todo',
                'bg-subtle text-muted' => $state === 'surrendered',
                'border-2 border-dashed border-line-strong bg-surface text-muted' => $state === 'skipped',
            ]) aria-hidden="true">
                @if ($state === 'done')
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"></path></svg>
                @elseif ($state === 'surrendered')
                    –
                @else
                    {{ $loop->iteration }}
                @endif
            </span>
            <span class="flex flex-col pt-1">
                <span @class(['font-bold', 'text-muted' => $state === 'todo'])>{{ $step['label'] }}</span>
                @if ($step['hint'] ?? null)
                    <span class="text-sm text-muted">{{ $step['hint'] }}</span>
                @endif
            </span>
        </li>
    @endforeach
</ol>
