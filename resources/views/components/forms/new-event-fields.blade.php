@props([
    // False when another form on the page was sent: its old input and errors are not ours.
    'submitted' => true,
])
{{-- U13: the fields of a new event, shared by the New event dialog and its full page. The link
     follows the name until the host types one (resources/js/event-link.js); once the name is
     done, the filled link is checked on the server too (U19), so a taken link shows at once. --}}
<div class="contents" x-data="eventLink({ reserved: {{ \Illuminate\Support\Js::from(\App\Rules\EventSlug::RESERVED) }}, link: {{ \Illuminate\Support\Js::from($submitted ? (string) old('slug', '') : '') }} })">
    <x-field name="name" :label="__('events.name')" :submitted="$submitted" required autofocus maxlength="100"
        x-on:input="nameTyped($event.target.value)" x-on:change="! own && link !== '' && $nextTick(() => $refs.link.dispatchEvent(new Event('change', { bubbles: true })))" />
    <x-field name="slug" :label="__('events.link')" :submitted="$submitted" :prefix="parse_url(config('app.url'), PHP_URL_HOST) . '/'" :hint="__('events.link_hint_new')" maxlength="60" autocapitalize="none" spellcheck="false"
        x-ref="link" x-model="link" x-on:input="linkTyped($event.target.value)" />
</div>
<x-theme-choice :value="$submitted ? old('screen_theme', 'dark') : 'dark'" :themes="\App\Enums\ScreenTheme::cases()" :hint="__('events.screen_theme_hint')" />
<x-toggle name="show_on_devices" :label="__('events.show_on_devices')" :hint="__('events.show_on_devices_hint')" :checked="$submitted && (bool) old('show_on_devices')" />
