<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Accounts\Username;
use App\Http\Concerns\LiveValidation;
use App\Models\User;
use App\Validation\AccountRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * C-7: editing an account; its own username and email stay valid when saved unchanged.
 */
final class UpdateUserRequest extends FormRequest
{
    use LiveValidation;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->account()) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $account = $this->account();

        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => AccountRules::username($account->id),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AccountRules::messages();
    }

    public function account(): User
    {
        $account = $this->route('user');

        if (! $account instanceof User) {
            abort(404);
        }

        return $account;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower($this->string('email')->trim()->toString()),
            'username' => Username::normalize($this->string('username')->toString()),
        ]);
    }
}
