<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Accounts\CreateAccount;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * C-1: host accounts, managed by super-admins.
 */
final class UserController
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(StoreUserRequest $request, CreateAccount $createAccount, #[CurrentUser] User $actor): RedirectResponse
    {
        $account = $createAccount->handle(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            superAdmin: false,
            actor: $actor,
        );

        return redirect()->route('admin.users.index')
            ->with('status', __('accounts.created', ['email' => $account->email]));
    }
}
