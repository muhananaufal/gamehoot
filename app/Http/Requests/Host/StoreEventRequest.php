<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Enums\EventStatus;
use App\Enums\ScreenTheme;
use App\Games\GameEngines;
use App\Http\Concerns\LiveValidation;
use App\Models\Event;
use App\Models\QuestionPack;
use App\Models\User;
use App\People\NameLines;
use App\Rules\EventSlug;
use App\Rules\HostPack;
use App\Support\EventLink;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create and settings forms share these rules; the settings form ignores the event's own link.
 */
final class StoreEventRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(GameEngines $engines): array
    {
        $event = $this->route('event');
        $unique = Rule::unique('events', 'slug');

        if ($event instanceof Event) {
            $unique = $unique->ignore($event->id);
        }

        // F10: printed QR codes and open phones and screens use the link of an open event.
        $keepLink = function (string $attribute, mixed $value, Closure $fail) use ($event): void {
            if ($event instanceof Event && $event->status === EventStatus::Open && $value !== $event->slug) {
                $fail(__('events.slug_locked'));
            }
        };

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', new EventSlug, $unique, $keepLink],
            'screen_theme' => ['required', Rule::enum(ScreenTheme::class)],
            'show_on_devices' => ['sometimes', 'boolean'],
        ];

        if ($event instanceof Event) {
            return $rules;
        }

        // U26: New event takes its names (one per line) and its games (packs, in the order
        // picked) too; both steps can be skipped.
        $user = $this->user();

        return [
            ...$rules,
            'names' => ['nullable', 'string'],
            'pack_ids' => ['nullable', 'array'],
            'pack_ids.*' => ['distinct', 'uuid', HostPack::of($user instanceof User ? $user : null, $engines)],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->route('event') instanceof Event || ! $this->filled('names')) {
                return;
            }

            foreach ($this->nameLines()->problems(null) as $problem) {
                $validator->errors()->add('names', $problem);
            }
        }];
    }

    /**
     * U26: the names of the new event, one per line, tidied and without empty lines.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return $this->nameLines()->names();
    }

    /**
     * U26: the packs of the new event's games, in the order picked.
     *
     * @return list<QuestionPack>
     */
    public function packs(): array
    {
        $ids = array_values(array_filter((array) $this->input('pack_ids', []), is_string(...)));
        $packs = QuestionPack::query()->whereKey($ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(fn (string $id): ?QuestionPack => $packs->get($id), $ids)));
    }

    private function nameLines(): NameLines
    {
        return NameLines::parse($this->string('names')->toString());
    }

    public function theme(): ScreenTheme
    {
        return ScreenTheme::from($this->string('screen_theme')->toString());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.unique' => __('events.slug_taken')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['slug' => __('events.link_attribute')];
    }

    /**
     * An empty link is made from the name and kept free (EventLink); a typed one is
     * validated as it is.
     */
    protected function prepareForValidation(): void
    {
        $slug = Str::lower($this->string('slug')->trim()->toString());

        if ($slug === '') {
            $event = $this->route('event');
            $slug = EventLink::fromName($this->string('name')->toString(), $event instanceof Event ? $event : null);
        }

        $this->merge(['slug' => $slug]);
    }
}
