<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Http\Concerns\LiveValidation;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

/**
 * T4, O7: the upload is checked by its content type, not its extension. K7: 1 MB, below
 * the PHP and nginx limits.
 */
final class ImportPeopleRequest extends FormRequest
{
    use LiveValidation {
        getRedirectUrl as liveRedirectUrl;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:1024', 'mimetypes:text/plain,text/csv,application/csv,text/x-csv'],
        ];
    }

    /**
     * B-4: a refused file is explained in the host's words, not the rule's.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => __('people.file_missing'),
            'file.file' => __('people.file_missing'),
            'file.mimetypes' => __('people.file_not_csv'),
            'file.max' => __('people.file_too_big'),
        ];
    }

    /**
     * B-4: a refused file goes back to the Upload step with its error under the field. "Back"
     * could be the Names page, where the Import modal closes and the error would be lost.
     */
    protected function getRedirectUrl(): string
    {
        $event = $this->route('event');

        return $event instanceof Event && ! $this->headers->has('X-Up-Validate')
            ? route('host.events.people.import.create', $event)
            : $this->liveRedirectUrl();
    }
}
