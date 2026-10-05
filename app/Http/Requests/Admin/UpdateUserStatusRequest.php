<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Concerns\LiveValidation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserStatusRequest extends FormRequest
{
    use LiveValidation;

    public function authorize(): bool
    {
        $account = $this->route('user');

        return $account instanceof User && ($this->user()?->can('changeStatus', $account) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['disabled' => ['required', 'boolean']];
    }
}
