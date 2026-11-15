<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Contracts\Responses;

use AbdulWahhabKhan\DeviceAuth\Data\VerifyPageData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the page. Bound by a frontend package through DeviceAuth::*View().
 */
interface VerifyViewResponse
{
    public function toResponse(Request $request, VerifyPageData $data): Response;
}
