@props(['title'])
<x-layouts.base :title="$title" class="bg-nav">
    <main class="flex min-h-dvh items-center justify-center p-4">
        <div class="flex w-full max-w-md flex-col gap-4 rounded-panel bg-surface p-6 text-ink sm:p-9">
            <x-logo :size="40" outline="stroke-surface" class="mb-2 text-2xl" />
            {{ $slot }}
        </div>
    </main>
</x-layouts.base>
