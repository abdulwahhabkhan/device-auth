<?php

namespace AbdulWahhabKhan\DeviceAuth;

use AbdulWahhabKhan\DeviceAuth\Console\ApproveDeviceCommand;
use AbdulWahhabKhan\DeviceAuth\Console\InstallCommand;
use AbdulWahhabKhan\DeviceAuth\Console\ListDevicesCommand;
use AbdulWahhabKhan\DeviceAuth\Console\RevokeDeviceCommand;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Http\Middleware\EnsureDeviceIsVerified;
use AbdulWahhabKhan\DeviceAuth\Http\Middleware\RequireDeviceConfirmation;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Services\AaguidDirectory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class DeviceAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/device-auth.php', 'device-auth');

        $this->app->singleton(AaguidDirectory::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('device.verified', EnsureDeviceIsVerified::class);
        $router->aliasMiddleware('device.confirm', RequireDeviceConfirmation::class);

        Gate::define(DeviceAuth::ABILITY, fn ($user) => DeviceAuth::isAdmin($user));

        RateLimiter::for('device-auth', fn (Request $request) => Limit::perMinute((int) config('device-auth.routes.throttle', 20))
            ->by($request->user()?->getAuthIdentifier() ?: $request->ip()));

        if (config('device-auth.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/device-auth.php');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->shareInertiaProps();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();

            $this->commands([
                InstallCommand::class,
                ListDevicesCommand::class,
                ApproveDeviceCommand::class,
                RevokeDeviceCommand::class,
            ]);
        }
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/device-auth.php' => config_path('device-auth.php'),
        ], 'device-auth-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'device-auth-migrations');

        // Pages land where Inertia looks for them; shared code next to them.
        $this->publishes([
            __DIR__.'/../resources/js/pages/device-auth' => resource_path('js/pages/device-auth'),
            __DIR__.'/../resources/js/device-auth' => resource_path('js/device-auth'),
        ], 'device-auth-react');
    }

    /**
     * Available to every page as usePage().props.deviceAuth.
     */
    private function shareInertiaProps(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('deviceAuth', function () {
            $request = request();
            $user = $request->user();

            if (! $user || ! $request->hasSession()) {
                return null;
            }

            $device = DeviceAuth::currentDevice($request);
            $isAdmin = DeviceAuth::isAdmin($user);
            $url = fn (string $name) => Route::has($name) ? route($name) : null;

            return [
                'enabled' => DeviceAuth::enabled(),
                'device' => $device ? [
                    'id' => $device->getKey(),
                    'name' => $device->name,
                    'reference' => $device->reference(),
                ] : null,
                'confirmRequired' => (bool) $request->session()->get(DeviceAuth::SESSION_CONFIRM_REQUIRED, false),
                'flash' => $request->session()->get('device_auth.flash'),
                'isAdmin' => $isAdmin,
                'pendingCount' => $isAdmin && $device
                    ? Device::query()->withStatus(DeviceStatus::Pending)->count()
                    : null,
                'urls' => [
                    'confirmStatus' => $url('device-auth.confirm.status'),
                    'confirmOptions' => $url('device-auth.confirm.options'),
                    'confirm' => $url('device-auth.confirm.store'),
                    'admin' => $isAdmin ? $url('device-auth.admin.index') : null,
                ],
            ];
        });
    }
}
