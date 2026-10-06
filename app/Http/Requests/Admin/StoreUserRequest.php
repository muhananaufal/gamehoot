<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Accounts\Username;
use App\Http\Concerns\LiveValidation;
use App\Models\User;
use App\Validation\AccountRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class StoreUserRequest extends FormRequest
{
    use LiveValidation;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return AccountRules::newAccount();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower($this->string('email')->trim()->toString()),
            'username' => Username::normalize($this->string('username')->toString()),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AccountRules::messages();
    }
}
