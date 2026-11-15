<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts\Responses;

use AbdulWahhabKhan\DeviceAuth\Data\DeviceIndexPageData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the page. Bound by a frontend package through DeviceAuth::*View().
 */
interface DeviceIndexViewResponse
{
    public function toResponse(Request $request, DeviceIndexPageData $data): Response;
}
