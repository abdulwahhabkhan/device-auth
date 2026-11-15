<?php

namespace AbdulWahhabKhan\DeviceAuth;

use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Entry point for apps using the package.
 *
 *   DeviceAuth::authorizeAdminsUsing(fn ($user) => $user->hasRole('it-admin'));
 *   DeviceAuth::currentDevice();   // the approved device this session verified with
 */
final class DeviceAuth
{
    public const SESSION_DEVICE = 'device_auth.device_id';

    public const SESSION_USER = 'device_auth.user_id';

    public const SESSION_CONFIRMED_AT = 'device_auth.confirmed_at';

    public const SESSION_CONFIRM_REQUIRED = 'device_auth.confirm_required';

    public const ABILITY = 'manage-device-auth';

    private const REQUEST_CACHE = 'device_auth.device';

    private static ?Closure $adminCallback = null;

    private static ?Closure $adminResolver = null;

    public static function authorizeAdminsUsing(?callable $callback): void
    {
        self::$adminCallback = $callback ? Closure::fromCallable($callback) : null;
    }

    public static function isAdmin(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        if (self::$adminCallback) {
            return (bool) call_user_func(self::$adminCallback, $user);
        }

        return (bool) data_get($user, config('device-auth.admin_attribute', 'is_superadmin'));
    }

    /**
     * Who gets notified about new requests. Defaults to users whose
     * admin attribute is true; supply a resolver for custom rules.
     */
    public static function resolveAdminsUsing(?callable $resolver): void
    {
        self::$adminResolver = $resolver ? Closure::fromCallable($resolver) : null;
    }

    /** @return iterable<int, mixed> */
    public static function admins(): iterable
    {
        if (self::$adminResolver) {
            return call_user_func(self::$adminResolver);
        }

        if (self::$adminCallback) {
            return []; // custom rule without a resolver: can't query, so nobody is notified
        }

        $model = self::userModel();

        return $model::query()->where(config('device-auth.admin_attribute', 'is_superadmin'), true)->get();
    }

    public static function enabled(): bool
    {
        return (bool) config('device-auth.enabled', true);
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    public static function userModel(): string
    {
        return config('device-auth.user_model')
            ?: config('auth.providers.users.model', 'App\\Models\\User');
    }

    /**
     * The active device this session verified with, or null.
     * Checked against the database every time, so a revoke works immediately.
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

        if (! $deviceId || $userId === null || (string) $session->get(self::SESSION_USER) !== (string) $userId) {
            return null;
        }

        // Looked up once per request (never across requests, so a revoke applies at once).
        if (! $request->attributes->has(self::REQUEST_CACHE)) {
            $request->attributes->set(self::REQUEST_CACHE, Device::query()->find($deviceId));
        }

        /** @var Device|null $device */
        $device = $request->attributes->get(self::REQUEST_CACHE);

        return $device?->isActive() ? $device : null;
    }

    public static function markVerified(Request $request, Device $device): void
    {
        $request->session()->regenerate();
        $request->session()->put([
            self::SESSION_DEVICE => $device->getKey(),
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
        $confirmedAt = (int) $request->session()->get(self::SESSION_CONFIRMED_AT, 0);

        return now()->getTimestamp() - $confirmedAt < (int) config('device-auth.confirm_timeout', 600);
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget([self::SESSION_DEVICE, self::SESSION_USER, self::SESSION_CONFIRMED_AT]);
        $request->attributes->remove(self::REQUEST_CACHE);
    }

    /**
     * Small, safe description of a user for the admin screens.
     *
     * @return array{id: mixed, name: string, email: string|null}|null
     */
    public static function describeUser(mixed $user): ?array
    {
        if (! $user) {
            return null;
        }

        $email = data_get($user, 'email');

        return [
            'id' => $user->getKey(),
            'name' => (string) (data_get($user, 'name') ?: $email ?: '#'.$user->getKey()),
            'email' => $email ? (string) $email : null,
        ];
    }
}
