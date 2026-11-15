<?php

namespace AbdulWahhabKhan\DeviceAuth\Http\Controllers;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceAccessRequested;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Http\Controllers\Concerns\RespondsWithDeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Http\Requests\DeviceAccessRequest;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Notifications\NewDeviceRequest;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceKeyService;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceMetadata;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;

/**
 * "Request access for this device": creates a key on the device and stores
 * its public half as pending until an administrator approves it.
 */
class DeviceRequestController extends Controller
{
    use RespondsWithDeviceStatus;

    public function options(Request $request, DeviceKeyService $keys): JsonResponse
    {
        $request->validate(['name' => ['required', 'string', 'max:100']]);

        if ($this->pendingCount($request) >= (int) config('device-auth.max_pending_per_user', 3)) {
            return response()->json([
                'status' => 'too_many_requests',
                'message' => 'You already have requests waiting for approval. Ask your administrator to review them first.',
            ], 429);
        }

        return response()->json($keys->beginRegistration($request->string('name')->trim()->toString()));
    }

    public function store(DeviceAccessRequest $request, DeviceKeyService $keys, DeviceMetadata $metadata): JsonResponse
    {
        try {
            $key = $keys->finishRegistration($request->credential());
        } catch (DeviceAuthException $e) {
            return $this->failed($e);
        }

        $device = Device::query()->create(
            $key->toAttributes()
            + $metadata->describe($request, (array) $request->validated('client', []))
            + [
                'name' => trim((string) $request->validated('name')),
                'request_note' => $request->validated('note'),
                'requested_by' => $request->user()->getAuthIdentifier(),
                'status' => DeviceStatus::Pending,
            ]
        );

        DeviceAccessRequested::dispatch($device, $request->user());

        if (config('device-auth.notifications')) {
            Notification::send(DeviceAuth::admins(), new NewDeviceRequest($device));
        }

        return response()->json([
            'status' => DeviceStatus::Pending->value,
            'device' => $device->publicSummary(),
            'contact' => config('device-auth.admin_contact'),
        ], 201);
    }

    private function pendingCount(Request $request): int
    {
        return Device::query()
            ->withStatus(DeviceStatus::Pending)
            ->where('requested_by', $request->user()->getAuthIdentifier())
            ->count();
    }
}
