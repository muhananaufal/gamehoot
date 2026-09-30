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
use App\Http\Controllers\Host\EventStateController as HostEventStateController;
use App\Http\Controllers\Host\GameController;
use App\Http\Controllers\Host\GameFinishController;
use App\Http\Controllers\Host\GameReloadController;
use App\Http\Controllers\Host\GameStartController;
use App\Http\Controllers\Host\JoinLockController;
use App\Http\Controllers\Host\LiveController;
use App\Http\Controllers\Host\OpenEventController;
use App\Http\Controllers\Host\PackQuestionController;
use App\Http\Controllers\Host\PackQuestionOrderController;
use App\Http\Controllers\Host\PersonClaimController;
use App\Http\Controllers\Host\PersonController;
use App\Http\Controllers\Host\PersonImportController;
use App\Http\Controllers\Host\PersonLinksController;
use App\Http\Controllers\Host\QuestionActionController;
use App\Http\Controllers\Host\QuestionPackController;
use App\Http\Controllers\Host\ReopenEventController;
use App\Http\Controllers\Host\TrashController;
use App\Http\Controllers\Join\EventStateController;
use App\Http\Controllers\Join\JoinController;
use App\Http\Controllers\Join\PersonalLinkController;
use App\Http\Controllers\Join\PlayController;
use App\Http\Controllers\Join\ScreenController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Views\PhoneStatus;
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

        // D-9: question packs, owner only.
        Route::get('/packs', [QuestionPackController::class, 'index'])->name('packs.index');
        Route::post('/packs', [QuestionPackController::class, 'store'])->name('packs.store');
        Route::prefix('/packs/{pack}')->whereUuid('pack')->name('packs.')->scopeBindings()->group(function (): void {
            Route::get('/', [QuestionPackController::class, 'show'])->name('show')->can('view', 'pack');
            Route::put('/', [QuestionPackController::class, 'update'])->name('update')->can('update', 'pack');
            Route::delete('/', [QuestionPackController::class, 'destroy'])->name('destroy')->can('delete', 'pack');
            Route::get('/questions/create', [PackQuestionController::class, 'create'])->name('questions.create')->can('update', 'pack');
            Route::post('/questions', [PackQuestionController::class, 'store'])->name('questions.store')->can('update', 'pack');
            Route::post('/questions/reorder', [PackQuestionOrderController::class, 'store'])->name('questions.reorder')->can('update', 'pack');
            Route::get('/questions/{question}/edit', [PackQuestionController::class, 'edit'])->name('questions.edit')->can('update', 'pack');
            Route::put('/questions/{question}', [PackQuestionController::class, 'update'])->name('questions.update')->can('update', 'pack');
            Route::delete('/questions/{question}', [PackQuestionController::class, 'destroy'])->name('questions.destroy')->can('update', 'pack');
        });

        Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
        Route::post('/trash/{event}/restore', [TrashController::class, 'update'])->name('trash.restore')
            ->withTrashed()->can('restore', 'event');

        Route::prefix('{event}')->whereUuid('event')->name('events.')->group(function (): void {
            Route::get('/', LiveController::class)->name('live')->can('view', 'event');
            Route::get('/state', HostEventStateController::class)->name('state')->can('view', 'event');
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

            // D-1, D-8, D-9, T8: the games of the event.
            Route::prefix('/games')->name('games.')->scopeBindings()->middleware('can:update,event')->group(function (): void {
                Route::get('/', [GameController::class, 'index'])->name('index');
                Route::post('/', [GameController::class, 'store'])->name('store');
                Route::delete('/{game}', [GameController::class, 'destroy'])->whereUuid('game')->name('destroy');
                Route::post('/{game}/reload', GameReloadController::class)->whereUuid('game')->name('reload');
                Route::post('/{game}/start', GameStartController::class)->whereUuid('game')->name('start');
                Route::post('/{game}/finish', GameFinishController::class)->whereUuid('game')->name('finish');
            });

            // F13, C-3: question actions, run by the engine of the game type.
            Route::post('/questions/{question}/{action}', QuestionActionController::class)
                ->whereUuid('question')->where('action', '[a-z-]+')->name('questions.action')->can('update', 'event');

            // B-3, B-4, B-6, T5: the master name list.
            Route::prefix('/people')->name('people.')->scopeBindings()->middleware('can:update,event')->group(function (): void {
                Route::get('/', [PersonController::class, 'index'])->name('index');
                Route::post('/', [PersonController::class, 'store'])->name('store');
                Route::get('/import', [PersonImportController::class, 'create'])->name('import.create');
                Route::post('/import', [PersonImportController::class, 'store'])->name('import.store');
                Route::post('/import/confirm', [PersonImportController::class, 'update'])->name('import.confirm');
                Route::get('/links.csv', PersonLinksController::class)->name('links');
                Route::put('/{person}', [PersonController::class, 'update'])->whereUuid('person')->name('update');
                Route::delete('/{person}', [PersonController::class, 'destroy'])->whereUuid('person')->name('destroy');
                Route::post('/{person}/release-claim', [PersonClaimController::class, 'store'])->whereUuid('person')->name('release-claim');
                Route::post('/{person}/regenerate-link', [PersonClaimController::class, 'update'])->whereUuid('person')->name('regenerate-link');
            });
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

// F1: the public snapshot answers in JSON, including the not-found error (K4).
Route::get('/{event:slug}/state', EventStateController::class)->where('event', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('join.state')
    ->missing(fn () => response()->json(['code' => 'EVENT_NOT_FOUND', 'message' => __('join.screens.not_found.title')], 404));

// F10: participant routes catch one path segment at the root, so they are registered last.
// The event is found by its link; unknown and deleted events get the EVENT_NOT_FOUND screen.
Route::prefix('{event:slug}')->where(['event' => '[a-z0-9]+(?:-[a-z0-9]+)*'])->name('join.')
    ->missing(fn () => PhoneStatus::notFound())
    ->group(function (): void {
        Route::get('/', [JoinController::class, 'index'])->name('index');
        Route::post('/claim', [JoinController::class, 'store'])->name('claim')->middleware('throttle:claim');
        Route::get('/j/{token}', PersonalLinkController::class)->name('personal')->where('token', '[A-Za-z0-9]{16}')->middleware('throttle:claim');
        Route::get('/play', PlayController::class)->name('play');
        Route::get('/screen', ScreenController::class)->name('screen');
    });
