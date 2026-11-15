<?php

declare(strict_types=1);

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Http\Controllers\Admin\DeviceAdminController;
use AbdulWahhabKhan\DeviceAuth\Http\Controllers\DeviceConfirmationController;
use AbdulWahhabKhan\DeviceAuth\Http\Controllers\DeviceRequestController;
use AbdulWahhabKhan\DeviceAuth\Http\Controllers\DeviceVerificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('device-auth.routes.middleware', ['web', 'auth']))->group(function (): void {

    // Signed in with a password, device not verified yet.
    Route::prefix(config('device-auth.routes.prefix', 'device'))->name('device-auth.')->group(function (): void {
        Route::get('verify', [DeviceVerificationController::class, 'show'])->name('verify');

        Route::middleware('throttle:device-auth')->group(function (): void {
            Route::post('verify/options', [DeviceVerificationController::class, 'options'])->name('verify.options');
            Route::post('verify', [DeviceVerificationController::class, 'store'])->name('verify.store');
            Route::post('request/options', [DeviceRequestController::class, 'options'])->name('request.options');
            Route::post('request', [DeviceRequestController::class, 'store'])->name('request.store');
        });

        // Re-confirming the current device before sensitive actions.
        Route::middleware('device.verified')->group(function (): void {
            Route::get('confirm', [DeviceConfirmationController::class, 'show'])->name('confirm');
            Route::get('confirm/status', [DeviceConfirmationController::class, 'status'])->name('confirm.status');

            Route::middleware('throttle:device-auth')->group(function (): void {
                Route::post('confirm/options', [DeviceConfirmationController::class, 'options'])->name('confirm.options');
                Route::post('confirm', [DeviceConfirmationController::class, 'store'])->name('confirm.store');
            });
        });
    });

    // Administrators: review device requests.
    Route::prefix(config('device-auth.routes.admin_prefix', 'admin/devices'))
        ->name('device-auth.admin.')
        ->middleware(['device.verified', 'can:'.DeviceAuth::ABILITY])
        ->group(function (): void {
            Route::get('/', [DeviceAdminController::class, 'index'])->name('index');
            Route::get('{device}', [DeviceAdminController::class, 'show'])->whereNumber('device')->name('show');

            Route::middleware('device.confirm')->group(function (): void {
                Route::post('{device}/approve', [DeviceAdminController::class, 'approve'])->whereNumber('device')->name('approve');
                Route::post('{device}/reject', [DeviceAdminController::class, 'reject'])->whereNumber('device')->name('reject');
                Route::post('{device}/revoke', [DeviceAdminController::class, 'revoke'])->whereNumber('device')->name('revoke');
            });
        });
});
