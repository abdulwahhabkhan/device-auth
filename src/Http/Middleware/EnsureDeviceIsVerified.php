<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Middleware;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "device.verified": the signed-in user must be on a device that proved
 * it holds an approved key in this session. Place it after "auth".
 */
class EnsureDeviceIsVerified
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! DeviceAuth::enabled() || $request->user() === null) {
            return $next($request);
        }

        if (DeviceAuth::currentDevice($request) instanceof Device) {
            return $next($request);
        }

        // Stale session (device revoked, or user switched): start over.
        DeviceAuth::forget($request);

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return new JsonResponse([
                'status' => 'device_not_verified',
                'message' => __('device-auth::messages.verify_required'),
                'verify_url' => route('device-auth.verify'),
            ], 403);
        }

        return redirect()->guest(route('device-auth.verify'));
    }
}
