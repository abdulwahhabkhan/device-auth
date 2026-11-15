<?php

declare(strict_types=1);

namespace App\Providers;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use App\Actions\DeviceAuth\ApproveDevice;
use App\Actions\DeviceAuth\BeginDeviceRegistration;
use App\Actions\DeviceAuth\BeginDeviceVerification;
use App\Actions\DeviceAuth\ConfirmDevice;
use App\Actions\DeviceAuth\ListDevices;
use App\Actions\DeviceAuth\RejectDevice;
use App\Actions\DeviceAuth\RequestDeviceAccess;
use App\Actions\DeviceAuth\RevokeDevice;
use App\Actions\DeviceAuth\VerifyDevice;
use Illuminate\Support\ServiceProvider;

class DeviceAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        DeviceAuth::beginRegistrationUsing(BeginDeviceRegistration::class);
        DeviceAuth::requestDeviceAccessUsing(RequestDeviceAccess::class);
        DeviceAuth::beginVerificationUsing(BeginDeviceVerification::class);
        DeviceAuth::verifyDevicesUsing(VerifyDevice::class);
        DeviceAuth::confirmDevicesUsing(ConfirmDevice::class);
        DeviceAuth::approveDevicesUsing(ApproveDevice::class);
        DeviceAuth::rejectDevicesUsing(RejectDevice::class);
        DeviceAuth::revokeDevicesUsing(RevokeDevice::class);
        DeviceAuth::listDevicesUsing(ListDevices::class);

        // Who may review devices (default: users whose `is_superadmin` attribute is true).
        // DeviceAuth::authorizeAdminsUsing(fn ($user) => $user->hasRole('it-admin'));
        // DeviceAuth::resolveAdminsUsing(fn () => User::role('it-admin')->get());
    }
}
