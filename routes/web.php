<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Host\DashboardController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use Illuminate\Support\Facades\Route;

// F10: every named route is registered before the catch-all /{event} participant routes.
Route::redirect('/', '/host');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');
});

Route::middleware(['auth', 'auth.session', EnsureAccountIsActive::class])->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/host', DashboardController::class)->name('host.dashboard');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index')->can('viewAny', User::class);
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create')->can('create', User::class);
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/password/edit', [UserPasswordController::class, 'edit'])->name('users.password.edit')->can('resetPassword', 'user');
        Route::put('/users/{user}/password', [UserPasswordController::class, 'update'])->name('users.password.update');
        Route::put('/users/{user}/status', [UserStatusController::class, 'update'])->name('users.status.update');
    });
});
