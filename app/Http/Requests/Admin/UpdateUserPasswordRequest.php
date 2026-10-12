<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Concerns\LiveValidation;
use App\Validation\AccountRules;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserPasswordRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['password' => AccountRules::password()];
    }
}
