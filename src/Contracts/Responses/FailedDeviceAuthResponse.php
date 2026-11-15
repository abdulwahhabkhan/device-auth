<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts\Responses;

use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface FailedDeviceAuthResponse
{
    public function toResponse(Request $request, DeviceAuthException $exception): Response;
}
