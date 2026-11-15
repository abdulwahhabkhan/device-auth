<?php

namespace AbdulWahhabKhan\DeviceAuth\Http\Middleware;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "device.confirm": the device must have been verified within the last
 * few minutes (config device-auth.confirm_timeout). Place it after
 * "device.verified".
 */
class RequireDeviceConfirmation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DeviceAuth::enabled() || DeviceAuth::recentlyConfirmed($request)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'status' => 'confirm_required',
                'message' => 'Confirm this device to continue.',
                'confirm_url' => route('device-auth.confirm'),
            ], 423);
        }

        // Page visits: confirm on a full page, then come back.
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('device-auth.confirm');
        }

        // Actions (approve, delete…): go back and let the page open the confirm dialog.
        return back()->with(DeviceAuth::SESSION_CONFIRM_REQUIRED, true);
    }
}
