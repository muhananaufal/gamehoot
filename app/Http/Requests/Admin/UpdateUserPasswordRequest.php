<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Validation\AccountRules;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('user');

        return $account instanceof User && ($this->user()?->can('resetPassword', $account) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['password' => AccountRules::password()];
    }
}
