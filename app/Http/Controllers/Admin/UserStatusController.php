<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Accounts\SetAccountStatus;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class UserStatusController
{
    public function update(UpdateUserStatusRequest $request, User $user, SetAccountStatus $setStatus, #[CurrentUser] User $actor): RedirectResponse
    {
        $disabled = $request->boolean('disabled');
        $setStatus->handle($user, $disabled, $actor);

        return redirect()->route('admin.users.index')
            ->with('status', __($disabled ? 'accounts.disabled_done' : 'accounts.enabled_done', ['email' => $user->email]));
    }
}
