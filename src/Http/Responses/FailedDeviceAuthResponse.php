<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Responses;

use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\FailedDeviceAuthResponse as FailedDeviceAuthResponseContract;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FailedDeviceAuthResponse implements FailedDeviceAuthResponseContract
{
    public function toResponse(Request $request, DeviceAuthException $exception): Response
    {
        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return new JsonResponse($exception->toArray(), $exception->status());
        }

        return back()->withErrors(['device' => $exception->getMessage()]);
    }
}
