<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Http\Concerns\LiveValidation;
use Illuminate\Foundation\Http\FormRequest;

final class JoinLockRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['locked' => ['required', 'boolean']];
    }
}
