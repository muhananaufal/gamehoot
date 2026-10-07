<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Http\Concerns\LiveValidation;
use App\Models\Event;
use App\People\NameLines;
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

            if ($this->lines()->names() === []) {
                $validator->errors()->add('names', __('validation.required', ['attribute' => __('people.names')]));

                return;
            }

            foreach ($this->lines()->problems($event) as $problem) {
                $validator->errors()->add('names', $problem);
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
        return $this->lines()->names();
    }

    private function lines(): NameLines
    {
        return NameLines::parse($this->string('names')->toString());
    }
}
