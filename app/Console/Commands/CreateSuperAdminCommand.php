<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Accounts\Username;
use App\Actions\Accounts\CreateAccount;
use App\Validation\AccountRules;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * O10: the credentials are typed at the prompt, never stored in code, seeders or shell history.
 */
#[Signature('pentahoot:create-super-admin')]
#[Description('Create a super-admin account from answers typed at the prompt')]
final class CreateSuperAdminCommand extends Command
{
    public function handle(CreateAccount $createAccount): int
    {
        $input = [
            'name' => $this->text($this->ask('Name')),
            // C-6: read in lower case, like every typed username.
            'username' => Username::normalize($this->text($this->ask('Username (lower case, no spaces)'))),
            'email' => $this->text($this->ask('Email')),
            'password' => $this->text($this->secret('Password (at least 12 characters, letters and digits)')),
            'password_confirmation' => $this->text($this->secret('Repeat the password')),
        ];

        $validator = Validator::make($input, AccountRules::newAccount(), AccountRules::messages());

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = $createAccount->handle($input['name'], $input['username'], $input['email'], $input['password'], superAdmin: true, actor: null);

        $this->info("Super-admin {$user->email} created.");

        return self::SUCCESS;
    }

    /**
     * An empty answer comes back as null; validation then reports the field as required.
     */
    private function text(mixed $answer): string
    {
        return is_string($answer) ? $answer : '';
    }
}
