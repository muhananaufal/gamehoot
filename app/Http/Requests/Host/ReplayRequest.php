<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Enums\ReplayKind;
use App\Http\Concerns\LiveValidation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * D-11: the moment to replay: its kind, its game and, for a question moment, its question.
 */
final class ReplayRequest extends FormRequest
{
    use LiveValidation;

    /**
     * @return array<string, list<string|ValidationRule|Enum>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(ReplayKind::class)],
            'game' => ['required', 'uuid'],
            'question' => ['nullable', 'uuid'],
        ];
    }

    public function kind(): ReplayKind
    {
        return ReplayKind::from($this->string('kind')->toString());
    }
}
