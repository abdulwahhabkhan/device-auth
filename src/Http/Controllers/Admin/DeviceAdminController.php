<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Http\Controllers\Admin;

use AbdulWahhabKhan\DeviceAuth\Contracts\ApprovesDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\ListsDevices;
use AbdulWahhabKhan\DeviceAuth\Contracts\RejectsDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceIndexViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceReviewedResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\DeviceShowViewResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\FailedDeviceAuthResponse;
use AbdulWahhabKhan\DeviceAuth\Contracts\RevokesDevice;
use AbdulWahhabKhan\DeviceAuth\Data\DeviceIndexPageData;
use AbdulWahhabKhan\DeviceAuth\Data\DeviceShowPageData;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * The "authorized_keys" screen: review, approve, reject and revoke devices.
 */
class DeviceAdminController extends Controller
{
    public function __construct(
        private readonly DeviceReviewedResponse $reviewed,
        private readonly FailedDeviceAuthResponse $failed,
    ) {}

    public function index(Request $request, ListsDevices $lister, DeviceIndexViewResponse $view): Response
    {
        return $view->toResponse($request, DeviceIndexPageData::fromRequest($request, $lister));
    }

    public function show(Request $request, Device $device, DeviceShowViewResponse $view): Response
    {
        return $view->toResponse($request, DeviceShowPageData::fromRequest($request, $device));
    }

    public function approve(Request $request, Device $device, ApprovesDevice $action): Response
    {
        return $this->review($request, DeviceStatus::Active, fn (): Device => $action->approve(
            $device, $request->user(), $request->all(), DeviceAuth::currentDevice($request),
        ));
    }

    public function reject(Request $request, Device $device, RejectsDevice $action): Response
    {
        return $this->review($request, DeviceStatus::Rejected, fn (): Device => $action->reject(
            $device, $request->user(), $request->all(), DeviceAuth::currentDevice($request),
        ));
    }

    public function revoke(Request $request, Device $device, RevokesDevice $action): Response
    {
        return $this->review($request, DeviceStatus::Revoked, fn (): Device => $action->revoke(
            $device, $request->user(), $request->all(), DeviceAuth::currentDevice($request),
        ));
    }

    /**
     * @param  Closure(): Device  $decide
     */
    private function review(Request $request, DeviceStatus $decision, Closure $decide): Response
    {
        try {
            $device = $decide();
        } catch (DeviceAuthException $e) {
            return $this->failed->toResponse($request, $e);
        }

        return $this->reviewed->toResponse($request, $device, $decision);
    }
}
