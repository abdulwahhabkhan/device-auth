<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Middleware;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "device.confirm": the device must have been verified within the last
 * few minutes (config device-auth.confirm_timeout). Place it after
 * "device.verified".
 */
class RequireDeviceConfirmation
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! DeviceAuth::enabled() || DeviceAuth::recentlyConfirmed($request)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return new JsonResponse([
                'status' => 'confirm_required',
                'message' => __('device-auth::messages.confirm_required'),
                'confirm_url' => route('device-auth.confirm'),
            ], 423);
        }

        // Page visits: confirm on a full page, then come back.
        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());

            return to_route('device-auth.confirm');
        }

        // Actions (approve, delete…): go back and let the page open the confirm dialog.
        return back()->with(DeviceAuth::SESSION_CONFIRM_REQUIRED, true);
    }
}
