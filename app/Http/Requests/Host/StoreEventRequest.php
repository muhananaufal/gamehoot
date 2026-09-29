<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Enums\ScreenTheme;
use App\Models\Event;
use App\Rules\EventSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Create and settings forms share these rules; the settings form ignores the event's own link.
 */
final class StoreEventRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $unique = Rule::unique('events', 'slug');

        if ($event instanceof Event) {
            $unique = $unique->ignore($event->id);
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', new EventSlug, $unique],
            'screen_theme' => ['required', Rule::enum(ScreenTheme::class)],
            'show_on_devices' => ['sometimes', 'boolean'],
        ];
    }

    public function theme(): ScreenTheme
    {
        return ScreenTheme::from($this->string('screen_theme')->toString());
    }

    /**
     * An empty link is made from the name; the result is validated like a typed one.
     */
    protected function prepareForValidation(): void
    {
        $slug = Str::lower($this->string('slug')->trim()->toString());

        if ($slug === '') {
            $slug = Str::limit(Str::slug($this->string('name')->toString()), 60, '');
            $slug = rtrim($slug, '-');
        }

        $this->merge(['slug' => $slug]);
    }
}
