<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use Illuminate\Foundation\Http\FormRequest;

/**
 * T4, O7: the upload is checked by its content type, not its extension. K7: 1 MB, below
 * the PHP and nginx limits.
 */
final class ImportPeopleRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:1024', 'mimetypes:text/plain,text/csv,application/csv,text/x-csv'],
        ];
    }
}
