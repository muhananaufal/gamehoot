<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Models\Event;
use App\People\DuplicateNames;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * B-4: the server checks the reviewed list again before anything is saved.
 */
final class ConfirmImportRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'names' => ['required', 'array', 'min:1'],
            'names.*' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $event = $this->route('event');

            if (! $event instanceof Event || $validator->errors()->isNotEmpty()) {
                return;
            }

            $names = $this->names();

            foreach (DuplicateNames::in($names, $event) as $index => $clash) {
                $validator->errors()->add("names.{$index}", $clash->message());
            }

            if ($event->people()->count() + count($names) > PersonNameRequest::MAX_NAMES) {
                $validator->errors()->add('names', __('people.too_many', ['max' => PersonNameRequest::MAX_NAMES]));
            }
        }];
    }

    /**
     * The preview page is the result of a POST, so "back" would lose it. The import page
     * rebuilds the review from the old input instead.
     */
    protected function getRedirectUrl(): string
    {
        $event = $this->route('event');

        return $event instanceof Event
            ? route('host.events.people.import.create', $event)
            : parent::getRedirectUrl();
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_filter((array) $this->input('names', []), is_string(...)));
    }
}
