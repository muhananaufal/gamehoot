@props(['title'])
<x-layouts.base :title="$title" class="bg-nav">
    <main class="flex min-h-dvh items-center justify-center p-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-panel bg-surface p-6 text-ink sm:p-9">
            <div class="mb-2 flex items-center justify-between gap-3">
                <x-logo :size="40" outline="stroke-surface" class="text-2xl" />
                <div class="flex items-center gap-1">
                    <x-motion-toggle class="text-muted hover:bg-subtle hover:text-ink" />
                    <x-theme-toggle class="text-muted hover:bg-subtle hover:text-ink" />
                </div>
            </div>
            {{ $slot }}
        </div>
    </main>
</x-layouts.base>
