<x-layouts.host :heading="false" :title="__('events.new_event')"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [__('events.new_event'), null]]">
    <div class="grid gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
        <section class="flex flex-col gap-4 rounded-card bg-surface p-5">
            <div>
                <h2 class="font-display text-title-s font-semibold">{{ __('events.setup_title') }}</h2>
                <p class="text-body-s text-muted">{{ __('events.setup_steps') }}</p>
            </div>
            <x-timeline :steps="[
                ['label' => __('events.step_name'), 'hint' => __('events.step_name_hint'), 'state' => 'current'],
                ['label' => __('events.step_names'), 'hint' => __('events.step_names_hint'), 'state' => 'todo'],
                ['label' => __('events.step_games'), 'hint' => __('events.step_games_hint'), 'state' => 'todo'],
                ['label' => __('events.step_open'), 'hint' => __('events.step_open_hint'), 'state' => 'todo'],
            ]" />
        </section>

        {{-- U13: what a New event modal shows; the steps beside it are only on the full page. --}}
        <div up-main="modal">
        <form method="POST" action="{{ route('host.events.store') }}" class="flex flex-col gap-5 rounded-card bg-surface p-6" novalidate>
            @csrf
            <h1 class="font-display text-title-s font-bold">{{ __('events.step_name') }}</h1>
            <x-forms.new-event-fields />
            <div class="flex justify-end gap-2.5 pt-2">
                <x-button variant="secondary" :href="route('host.dashboard')" up-dismiss="">{{ __('ui.cancel') }}</x-button>
                <x-button type="submit">{{ __('events.create') }}</x-button>
            </div>
        </form>
        </div>
    </div>
</x-layouts.host>
