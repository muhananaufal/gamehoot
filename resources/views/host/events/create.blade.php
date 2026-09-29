<x-layouts.host :title="__('events.new_event')"
    :breadcrumbs="[[__('host.dashboard.title'), route('host.dashboard')], [__('events.new_event'), null]]">
    <div class="grid gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
        <section class="flex flex-col gap-4 rounded-card bg-surface p-5">
            <div>
                <h2 class="font-display text-xl font-semibold">{{ __('events.setup_title') }}</h2>
                <p class="text-sm text-muted">{{ __('events.setup_steps') }}</p>
            </div>
            <x-timeline :steps="[
                ['label' => __('events.step_name'), 'hint' => __('events.step_name_hint'), 'state' => 'current'],
                ['label' => __('events.step_names'), 'hint' => __('events.step_names_hint'), 'state' => 'todo'],
                ['label' => __('events.step_games'), 'hint' => __('events.step_games_hint'), 'state' => 'todo'],
                ['label' => __('events.step_open'), 'hint' => __('events.step_open_hint'), 'state' => 'todo'],
            ]" />
        </section>

        <form method="POST" action="{{ route('host.events.store') }}" class="flex max-w-2xl flex-col gap-5 rounded-card bg-surface p-6" novalidate>
            @csrf
            <h1 class="font-display text-2xl font-bold">{{ __('events.step_name') }}</h1>
            <x-field name="name" :label="__('events.name')" required autofocus maxlength="100" />
            <x-field name="slug" :label="__('events.link')" :prefix="parse_url(config('app.url'), PHP_URL_HOST) . '/'" :hint="__('events.link_hint_new')" maxlength="60" autocapitalize="none" spellcheck="false" />
            <x-theme-choice value="dark" :themes="$themes" :hint="__('events.screen_theme_hint')" />
            <x-toggle name="show_on_devices" :label="__('events.show_on_devices')" :hint="__('events.show_on_devices_hint')" />
            <div class="flex justify-end gap-2.5 pt-2">
                <x-button variant="secondary" :href="route('host.dashboard')">{{ __('ui.cancel') }}</x-button>
                <x-button type="submit">{{ __('events.create') }}</x-button>
            </div>
        </form>
    </div>
</x-layouts.host>
