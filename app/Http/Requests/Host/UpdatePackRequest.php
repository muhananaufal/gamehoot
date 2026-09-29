<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePackRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:100']];
    }
}
