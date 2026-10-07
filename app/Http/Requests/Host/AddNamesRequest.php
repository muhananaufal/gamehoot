<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Http\Concerns\LiveValidation;
use App\Models\Event;
use App\People\DuplicateNames;
use App\People\PersonName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * B-4: Add names takes one name per line. The page checks the same rules before sending; the
 * server checks them again: no empty list, no name over 100 characters, no name already in
 * the event or twice in the lines (case and spaces ignored), no more than 500 names (B-3).
 */
final class AddNamesRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['names' => ['required', 'string']];
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

            if ($names === []) {
                $validator->errors()->add('names', __('validation.required', ['attribute' => __('people.names')]));

                return;
            }

            // A message names the line the host sees, empty lines counted, like the page does.
            $lineOf = array_keys($this->lines());

            foreach (DuplicateNames::in($names, $event) as $index => $clash) {
                $validator->errors()->add('names', __('people.on_line', ['line' => $lineOf[$index], 'problem' => $clash->lineMessage($lineOf)]));
            }

            if ($event->people()->count() + count($names) > PersonNameRequest::MAX_NAMES) {
                $validator->errors()->add('names', __('people.too_many', ['max' => PersonNameRequest::MAX_NAMES]));
            }
        }];
    }

    /**
     * The names typed, one per line, tidied (PersonName::display) and without empty lines.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values($this->lines());
    }

    /**
     * The names keyed by their line in the text area (from 1), empty lines left out.
     *
     * @return array<int, string>
     */
    private function lines(): array
    {
        $lines = [];

        foreach (preg_split('/\r\n|\n|\r/', $this->string('names')->toString()) ?: [] as $index => $typed) {
            $name = PersonName::display($typed);

            if ($name !== '') {
                $lines[$index + 1] = $name;
            }
        }

        return $lines;
    }
}
