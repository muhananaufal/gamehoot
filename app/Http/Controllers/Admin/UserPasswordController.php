<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Accounts\ResetPassword;
use App\Http\Requests\Admin\UpdateUserPasswordRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * C-5: password reset by a super-admin, handed over in person.
 */
final class UserPasswordController
{
    public function edit(User $user): View
    {
        return view('admin.users.password', ['account' => $user]);
    }

    public function update(UpdateUserPasswordRequest $request, User $user, ResetPassword $resetPassword, #[CurrentUser] User $actor): RedirectResponse
    {
        $resetPassword->handle($user, $request->string('password')->toString(), $actor);

        return redirect()->route('admin.users.index')
            ->with('status', __('accounts.password_reset', ['email' => $user->email]));
    }
}
