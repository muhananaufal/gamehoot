<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\TrashController as AdminTrashController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\Admin\UserStatusController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Host\CloseEventController;
use App\Http\Controllers\Host\DashboardController;
use App\Http\Controllers\Host\EventCohostController;
use App\Http\Controllers\Host\EventController;
use App\Http\Controllers\Host\EventOwnerController;
use App\Http\Controllers\Host\JoinLockController;
use App\Http\Controllers\Host\OpenEventController;
use App\Http\Controllers\Host\ReopenEventController;
use App\Http\Controllers\Host\TrashController;
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

    Route::prefix('host')->name('host.')->group(function (): void {
        Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
        Route::post('/events', [EventController::class, 'store'])->name('events.store');

        Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
        Route::post('/trash/{event}/restore', [TrashController::class, 'update'])->name('trash.restore')
            ->withTrashed()->can('restore', 'event');

        Route::prefix('{event}')->whereUuid('event')->name('events.')->group(function (): void {
            Route::get('/settings', [EventController::class, 'edit'])->name('edit')->can('update', 'event');
            Route::put('/settings', [EventController::class, 'update'])->name('update')->can('update', 'event');
            Route::delete('/', [EventController::class, 'destroy'])->name('destroy')->can('delete', 'event');

            Route::post('/cohosts', [EventCohostController::class, 'store'])->name('cohosts.store')->can('manageCohosts', 'event');
            Route::delete('/cohosts/{user}', [EventCohostController::class, 'destroy'])->name('cohosts.destroy')->can('manageCohosts', 'event');
            Route::post('/transfer', [EventOwnerController::class, 'store'])->name('transfer')->can('transfer', 'event');

            Route::post('/open', OpenEventController::class)->name('open')->can('update', 'event');
            Route::post('/close', CloseEventController::class)->name('close')->can('close', 'event');
            Route::post('/reopen', ReopenEventController::class)->name('reopen')->can('reopen', 'event');
            Route::post('/join-lock', JoinLockController::class)->name('join-lock')->can('lockJoining', 'event');
        });
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name('users.index')->can('viewAny', User::class);
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create')->can('create', User::class);
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/password/edit', [UserPasswordController::class, 'edit'])->name('users.password.edit')->can('resetPassword', 'user');
        Route::put('/users/{user}/password', [UserPasswordController::class, 'update'])->name('users.password.update');
        Route::put('/users/{user}/status', [UserStatusController::class, 'update'])->name('users.status.update');

        Route::get('/trash', [AdminTrashController::class, 'index'])->name('trash.index')->can('viewAny', User::class);
        Route::post('/trash/{event}/restore', [AdminTrashController::class, 'update'])->name('trash.restore')
            ->withTrashed()->can('restore', 'event');
    });
});
