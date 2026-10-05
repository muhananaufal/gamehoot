<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Accounts\CreateAccount;
use App\Actions\Accounts\DeleteAccount;
use App\Actions\Accounts\UpdateAccount;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
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
            // C-7: an account that owns events (deleted ones too) cannot be deleted.
            'users' => User::query()->withCount(['ownedEvents' => fn (Builder $events) => $events->withTrashed()])->orderBy('name')->get(),
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
            $request->string('username')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            superAdmin: false,
            actor: $actor,
        );

        return redirect()->route('admin.users.index')
            ->with('status', __('accounts.created', ['email' => $account->email]));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['account' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateAccount $updateAccount, #[CurrentUser] User $actor): RedirectResponse
    {
        $updateAccount->handle(
            $user,
            $request->string('name')->toString(),
            $request->string('username')->toString(),
            $request->string('email')->toString(),
            $actor,
        );

        return redirect()->route('admin.users.index')->with('status', __('accounts.updated', ['name' => $user->name]));
    }

    public function destroy(User $user, DeleteAccount $deleteAccount, #[CurrentUser] User $actor): RedirectResponse
    {
        $deleteAccount->handle($user, $actor);

        return redirect()->route('admin.users.index')->with('status', __('accounts.deleted', ['name' => $user->name]));
    }
}
