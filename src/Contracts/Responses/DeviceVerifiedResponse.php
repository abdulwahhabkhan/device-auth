<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts\Responses;

use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface DeviceVerifiedResponse
{
    public function toResponse(Request $request, Device $device): Response;
}
