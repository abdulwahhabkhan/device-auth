<?php

namespace AbdulWahhabKhan\DeviceAuth\Http\Middleware;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "device.verified": the signed-in user must be on a device that proved
 * it holds an approved key in this session. Place it after "auth".
 */
class EnsureDeviceIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DeviceAuth::enabled() || ! $request->user()) {
            return $next($request);
        }

        if (DeviceAuth::currentDevice($request)) {
            return $next($request);
        }

        // Stale session (device revoked, or user switched): start over.
        DeviceAuth::forget($request);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'status' => 'device_not_verified',
                'message' => 'Verify this device to continue.',
                'verify_url' => route('device-auth.verify'),
            ], 403);
        }

        return redirect()->guest(route('device-auth.verify'));
    }
}
