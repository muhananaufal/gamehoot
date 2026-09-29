<?php

declare(strict_types=1);

namespace App\Http\Requests\Join;

use Illuminate\Foundation\Http\FormRequest;

final class ClaimRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['person' => ['required', 'uuid']];
    }
}
