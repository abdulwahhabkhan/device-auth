<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth;

use AbdulWahhabKhan\DeviceAuth\Actions\ApproveDevice;
use AbdulWahhabKhan\DeviceAuth\Actions\BeginDeviceRegistration;
use AbdulWahhabKhan\DeviceAuth\Actions\BeginDeviceVerification;
use AbdulWahhabKhan\DeviceAuth\Actions\ConfirmDevice;
use AbdulWahhabKhan\DeviceAuth\Actions\ListDevices;
use AbdulWahhabKhan\DeviceAuth\Actions\RejectDevice;
use AbdulWahhabKhan\DeviceAuth\Actions\RequestDeviceAccess;
use AbdulWahhabKhan\DeviceAuth\Actions\RevokeDevice;
use AbdulWahhabKhan\DeviceAuth\Actions\VerifyDevice;
use AbdulWahhabKhan\DeviceAuth\Console\ApproveDeviceCommand;
use AbdulWahhabKhan\DeviceAuth\Console\InstallCommand;
use AbdulWahhabKhan\DeviceAuth\Console\ListDevicesCommand;
use AbdulWahhabKhan\DeviceAuth\Console\RevokeDeviceCommand;
use AbdulWahhabKhan\DeviceAuth\Contracts\ApprovesDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\BeginsDeviceRegistration;
use AbdulWahhabKhan\DeviceAuth\Contracts\BeginsDeviceVerification;
use AbdulWahhabKhan\DeviceAuth\Contracts\ConfirmsDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\ListsDevices;
use AbdulWahhabKhan\DeviceAuth\Contracts\RejectsDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\RequestsDeviceAccess;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\ConfirmViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceAccessRequestedResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceConfirmedResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceIndexViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceReviewedResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceShowViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceVerifiedResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\FailedDeviceAuthResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\VerifyViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\RevokesDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\VerifiesDevice;
use AbdulWahhabKhan\DeviceAuth\Http\Middleware\EnsureDeviceIsVerified;
use AbdulWahhabKhan\DeviceAuth\Http\Middleware\RequireDeviceConfirmation;
use AbdulWahhabKhan\DeviceAuth\Http\Responses;
use AbdulWahhabKhan\DeviceAuth\Http\Responses\ViewResponse;
use AbdulWahhabKhan\DeviceAuth\Services\AaguidDirectory;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceKeyService;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceMetadata;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class DeviceAuthServiceProvider extends ServiceProvider
{
    /**
     * Default action for every contract. An app overrides any of them
     * with DeviceAuth::*Using(), usually from its published provider.
     *
     * @var array<class-string, class-string>
     */
    private const array ACTIONS = [
        BeginsDeviceRegistration::class => BeginDeviceRegistration::class,
        RequestsDeviceAccess::class => RequestDeviceAccess::class,
        BeginsDeviceVerification::class => BeginDeviceVerification::class,
        VerifiesDevice::class => VerifyDevice::class,
        ConfirmsDevice::class => ConfirmDevice::class,
        ApprovesDevice::class => ApproveDevice::class,
        RejectsDevice::class => RejectDevice::class,
        RevokesDevice::class => RevokeDevice::class,
        ListsDevices::class => ListDevices::class,
    ];

    /** @var array<class-string, class-string> */
    private const array RESPONSES = [
        VerifyViewResponse::class => ViewResponse::class,
        ConfirmViewResponse::class => ViewResponse::class,
        DeviceIndexViewResponse::class => ViewResponse::class,
        DeviceShowViewResponse::class => ViewResponse::class,
        DeviceVerifiedResponse::class => Responses\DeviceVerifiedResponse::class,
        DeviceConfirmedResponse::class => Responses\DeviceConfirmedResponse::class,
        DeviceAccessRequestedResponse::class => Responses\DeviceAccessRequestedResponse::class,
        DeviceReviewedResponse::class => Responses\DeviceReviewedResponse::class,
        FailedDeviceAuthResponse::class => Responses\FailedDeviceAuthResponse::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/device-auth.php', 'device-auth');

        $this->app->singleton(AaguidDirectory::class);
        $this->app->singleton(DeviceKeyService::class);
        $this->app->singleton(DeviceMetadata::class);

        foreach ([...self::ACTIONS, ...self::RESPONSES] as $contract => $implementation) {
            $this->app->singletonIf($contract, $implementation);
        }
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('device.verified', EnsureDeviceIsVerified::class);
        $router->aliasMiddleware('device.confirm', RequireDeviceConfirmation::class);

        Gate::define(DeviceAuth::ABILITY, static fn (?Authenticatable $user): bool => DeviceAuth::isAdmin($user));

        RateLimiter::for('device-auth', static function (Request $request): Limit {
            $perMinute = config('device-auth.routes.throttle', 20);

            return Limit::perMinute(is_numeric($perMinute) ? (int) $perMinute : 20)
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'device-auth');

        if (DeviceAuth::$registersRoutes && (bool) config('device-auth.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/device-auth.php');
        }

        if (DeviceAuth::$runsMigrations) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

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

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'device-auth-migrations');

        $actions = [];

        foreach (glob(__DIR__.'/../stubs/actions/*.php') ?: [] as $stub) {
            $actions[$stub] = app_path('Actions/DeviceAuth/'.basename($stub));
        }

        $this->publishes($actions, 'device-auth-actions');

        $this->publishes([
            __DIR__.'/../stubs/DeviceAuthServiceProvider.php' => app_path('Providers/DeviceAuthServiceProvider.php'),
        ], 'device-auth-provider');

        $this->publishes([
            __DIR__.'/../routes/device-auth.php' => base_path('routes/device-auth.php'),
        ], 'device-auth-routes');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/device-auth'),
        ], 'device-auth-lang');
    }
}
