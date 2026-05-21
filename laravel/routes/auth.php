<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

$legacySiteUrl = rtrim((string) config('legacy.site_url', ''), '/');

Route::middleware('guest')->group(function () use ($legacySiteUrl) {
    Route::get('register', function () use ($legacySiteUrl): RedirectResponse|View {
        if ($legacySiteUrl !== '') {
            return redirect()->away($legacySiteUrl.'/index.php?page=register#register-section');
        }

        return app(RegisteredUserController::class)->create(request());
    })->name('register');

    if ($legacySiteUrl === '') {
        Route::post('register', [RegisteredUserController::class, 'store']);
    }

    Route::get('login', function () use ($legacySiteUrl): RedirectResponse|View {
        if ($legacySiteUrl !== '') {
            return redirect()->away($legacySiteUrl.'/index.php?page=login');
        }

        return app(AuthenticatedSessionController::class)->create();
    })->name('login');

    if ($legacySiteUrl === '') {
        Route::post('login', [AuthenticatedSessionController::class, 'store']);
    }

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
