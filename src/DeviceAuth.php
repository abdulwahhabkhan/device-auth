<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth;

use AbdulWahhabKhan\DeviceAuth\Contracts\ApprovesDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\BeginsDeviceRegistration;
use AbdulWahhabKhan\DeviceAuth\Contracts\BeginsDeviceVerification;
use AbdulWahhabKhan\DeviceAuth\Contracts\ConfirmsDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\ListsDevices;
use AbdulWahhabKhan\DeviceAuth\Contracts\RejectsDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\RequestsDeviceAccess;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\ConfirmViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceIndexViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceShowViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\VerifyViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\RevokesDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\VerifiesDevice;
use AbdulWahhabKhan\DeviceAuth\Data\DeviceAuthState;
use AbdulWahhabKhan\DeviceAuth\Data\PageData;
use AbdulWahhabKhan\DeviceAuth\Http\Responses\ViewResponse;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entry point for apps using the package, in the style of Laravel Fortify.
 *
 *   DeviceAuth::approveDevicesUsing(ApproveDevice::class);
 *   DeviceAuth::verifyView(fn (Request $request, VerifyPageData $data) => view('auth.verify-device', $data->toArray()));
 *   DeviceAuth::authorizeAdminsUsing(fn ($user) => $user->hasRole('it-admin'));
 *   DeviceAuth::currentDevice();   // the approved device this session verified with
 *
 * @phpstan-type UserSummary array{id: int|string, name: string, email: string|null}
 * @phpstan-type ViewCallback (Closure(Request, PageData): (Response|Responsable|View|string))|view-string
 */
final class DeviceAuth
{
    public const SESSION_DEVICE = 'device_auth.device_id';

    public const SESSION_USER = 'device_auth.user_id';

    public const SESSION_CONFIRMED_AT = 'device_auth.confirmed_at';

    public const SESSION_CONFIRM_REQUIRED = 'device_auth.confirm_required';

    public const SESSION_FLASH = 'device_auth.flash';

    public const ABILITY = 'manage-device-auth';

    private const REQUEST_CACHE = 'device_auth.device';

    /** Register the package routes (disable with ignoreRoutes()). */
    public static bool $registersRoutes = true;

    /** Run the package migrations (disable with ignoreMigrations()). */
    public static bool $runsMigrations = true;

    /** @var (Closure(Authenticatable): bool)|null */
    private static ?Closure $adminCallback = null;

    /** @var (Closure(): iterable<int, mixed>)|null */
    private static ?Closure $adminResolver = null;

    // ---------------------------------------------------------------------
    // Actions (Fortify style): swap any step for your own class.
    // ---------------------------------------------------------------------

    /** @param class-string<BeginsDeviceRegistration> $action */
    public static function beginRegistrationUsing(string $action): void
    {
        app()->singleton(BeginsDeviceRegistration::class, $action);
    }

    /** @param class-string<RequestsDeviceAccess> $action */
    public static function requestDeviceAccessUsing(string $action): void
    {
        app()->singleton(RequestsDeviceAccess::class, $action);
    }

    /** @param class-string<BeginsDeviceVerification> $action */
    public static function beginVerificationUsing(string $action): void
    {
        app()->singleton(BeginsDeviceVerification::class, $action);
    }

    /** @param class-string<VerifiesDevice> $action */
    public static function verifyDevicesUsing(string $action): void
    {
        app()->singleton(VerifiesDevice::class, $action);
    }

    /** @param class-string<ConfirmsDevice> $action */
    public static function confirmDevicesUsing(string $action): void
    {
        app()->singleton(ConfirmsDevice::class, $action);
    }

    /** @param class-string<ApprovesDevice> $action */
    public static function approveDevicesUsing(string $action): void
    {
        app()->singleton(ApprovesDevice::class, $action);
    }

    /** @param class-string<RejectsDevice> $action */
    public static function rejectDevicesUsing(string $action): void
    {
        app()->singleton(RejectsDevice::class, $action);
    }

    /** @param class-string<RevokesDevice> $action */
    public static function revokeDevicesUsing(string $action): void
    {
        app()->singleton(RevokesDevice::class, $action);
    }

    /** @param class-string<ListsDevices> $action */
    public static function listDevicesUsing(string $action): void
    {
        app()->singleton(ListsDevices::class, $action);
    }

    // ---------------------------------------------------------------------
    // Views: a frontend package (or your app) decides how pages render.
    // A string is a Blade view name; a closure receives the request and data.
    // ---------------------------------------------------------------------

    /** @param ViewCallback $view */
    public static function verifyView(Closure|string $view): void
    {
        app()->singleton(VerifyViewResponse::class, static fn (): ViewResponse => new ViewResponse($view));
    }

    /** @param ViewCallback $view */
    public static function confirmView(Closure|string $view): void
    {
        app()->singleton(ConfirmViewResponse::class, static fn (): ViewResponse => new ViewResponse($view));
    }

    /** @param ViewCallback $view */
    public static function deviceIndexView(Closure|string $view): void
    {
        app()->singleton(DeviceIndexViewResponse::class, static fn (): ViewResponse => new ViewResponse($view));
    }

    /** @param ViewCallback $view */
    public static function deviceShowView(Closure|string $view): void
    {
        app()->singleton(DeviceShowViewResponse::class, static fn (): ViewResponse => new ViewResponse($view));
    }

    /** Register your own copy of routes/device-auth.php instead. */
    public static function ignoreRoutes(): void
    {
        self::$registersRoutes = false;
    }

    /** Publish the migrations and run them as part of your app instead. */
    public static function ignoreMigrations(): void
    {
        self::$runsMigrations = false;
    }

    // ---------------------------------------------------------------------
    // Administrators
    // ---------------------------------------------------------------------

    /** @param (callable(Authenticatable): bool)|null $callback */
    public static function authorizeAdminsUsing(?callable $callback): void
    {
        self::$adminCallback = $callback === null ? null : Closure::fromCallable($callback);
    }

    public static function isAdmin(?Authenticatable $user): bool
    {
        if (! $user instanceof Authenticatable) {
            return false;
        }

        if (self::$adminCallback instanceof Closure) {
            return (self::$adminCallback)($user);
        }

        return (bool) data_get($user, self::adminAttribute());
    }

    /**
     * Who gets notified about new requests. Defaults to users whose
     * admin attribute is true; supply a resolver for custom rules.
     *
     * @param  (callable(): iterable<int, mixed>)|null  $resolver
     */
    public static function resolveAdminsUsing(?callable $resolver): void
    {
        self::$adminResolver = $resolver === null ? null : Closure::fromCallable($resolver);
    }

    /** @return iterable<int, mixed> */
    public static function admins(): iterable
    {
        if (self::$adminResolver instanceof Closure) {
            return (self::$adminResolver)();
        }

        if (self::$adminCallback instanceof Closure) {
            return []; // custom rule without a resolver: can't query, so nobody is notified
        }

        $model = self::userModel();

        return $model::query()->where(self::adminAttribute(), true)->get();
    }

    // ---------------------------------------------------------------------
    // Configuration helpers
    // ---------------------------------------------------------------------

    public static function enabled(): bool
    {
        return (bool) config('device-auth.enabled', true);
    }

    /** @return class-string<Model> */
    public static function userModel(): string
    {
        return config('device-auth.user_model') ?: config('auth.providers.users.model', 'App\\Models\\User');
    }

    /** @param 'devices'|'logins' $name */
    public static function table(string $name): string
    {
        $table = config('device-auth.tables.'.$name);

        return is_string($table) && $table !== '' ? $table : 'device_auth_'.$name;
    }

    public static function adminContact(): ?string
    {
        $contact = config('device-auth.admin_contact');

        return is_string($contact) && $contact !== '' ? $contact : null;
    }

    public static function home(): string
    {
        $home = config('device-auth.home', '/');

        return is_string($home) ? $home : '/';
    }

    /** @return list<string> */
    public static function notificationChannels(): array
    {
        $channels = config('device-auth.notifications', []);

        return is_array($channels) ? array_values(array_filter($channels, is_string(...))) : [];
    }

    private static function adminAttribute(): string
    {
        $attribute = config('device-auth.admin_attribute', 'is_superadmin');

        return is_string($attribute) ? $attribute : 'is_superadmin';
    }

    // ---------------------------------------------------------------------
    // Session state
    // ---------------------------------------------------------------------

    /**
     * The active device this session verified with, or null.
     * Checked against the database every request, so a revoke works immediately.
     */
    public static function currentDevice(?Request $request = null): ?Device
    {
        $request ??= request();

        if (! $request->hasSession()) {
            return null;
        }

        $session = $request->session();
        $deviceId = $session->get(self::SESSION_DEVICE);
        $userId = $request->user()?->getAuthIdentifier();

        if (! is_scalar($deviceId) || $userId === null || (string) $session->get(self::SESSION_USER) !== (string) $userId) {
            return null;
        }

        // Looked up once per request (never across requests, so a revoke applies at once).
        if (! $request->attributes->has(self::REQUEST_CACHE)) {
            $request->attributes->set(self::REQUEST_CACHE, Device::query()->find($deviceId));
        }

        $device = $request->attributes->get(self::REQUEST_CACHE);

        return $device instanceof Device && $device->isActive() ? $device : null;
    }

    public static function markVerified(Request $request, Device $device): void
    {
        $request->session()->regenerate();
        $request->session()->put([
            self::SESSION_DEVICE => $device->id,
            self::SESSION_USER => (string) $request->user()?->getAuthIdentifier(),
            self::SESSION_CONFIRMED_AT => now()->getTimestamp(),
        ]);

        $request->attributes->set(self::REQUEST_CACHE, $device);
    }

    public static function markConfirmed(Request $request): void
    {
        $request->session()->put(self::SESSION_CONFIRMED_AT, now()->getTimestamp());
    }

    public static function recentlyConfirmed(Request $request): bool
    {
        $confirmedAt = $request->session()->get(self::SESSION_CONFIRMED_AT, 0);
        $timeout = config('device-auth.confirm_timeout', 600);

        return now()->getTimestamp() - (is_numeric($confirmedAt) ? (int) $confirmedAt : 0)
            < (is_numeric($timeout) ? (int) $timeout : 600);
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget([self::SESSION_DEVICE, self::SESSION_USER, self::SESSION_CONFIRMED_AT]);
        $request->attributes->remove(self::REQUEST_CACHE);
    }

    /** Shared state for every page (Inertia prop, Blade variable). */
    public static function state(?Request $request = null): ?DeviceAuthState
    {
        return DeviceAuthState::fromRequest($request ?? request());
    }

    /**
     * Small, safe description of a user for the admin screens.
     *
     * @return UserSummary|null
     */
    public static function describeUser(mixed $user): ?array
    {
        if (! $user instanceof Model) {
            return null;
        }

        /** @var int|string $id */
        $id = $user->getKey();
        $email = data_get($user, 'email');
        $name = data_get($user, 'name');

        return [
            'id' => $id,
            'name' => is_string($name) && $name !== '' ? $name : (is_string($email) && $email !== '' ? $email : '#'.$id),
            'email' => is_string($email) && $email !== '' ? $email : null,
        ];
    }
}
