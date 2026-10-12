<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Concerns\LiveValidation;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserStatusRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['disabled' => ['required', 'boolean']];
    }
}
