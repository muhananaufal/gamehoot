<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * D-3: the owner types the event name to confirm the delete.
 */
final class DeleteEventRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $event = $this->route('event');
        $name = $event instanceof Event ? $event->name : '';

        return [
            'confirm_name' => ['required', 'string', Rule::in([$name])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm_name.in' => __('events.delete_confirm_mismatch')];
    }
}
