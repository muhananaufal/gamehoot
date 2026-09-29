<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Rules\EventSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * D-3: restore under the original link, or a new one when it was taken meanwhile.
 */
final class RestoreEventRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', new EventSlug, 'unique:events,slug'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.unique' => __('events.restore_slug_taken')];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::lower($this->string('slug')->trim()->toString())]);
    }
}
