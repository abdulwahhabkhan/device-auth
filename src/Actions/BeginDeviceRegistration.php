<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Actions;

use AbdulWahhabKhan\DeviceAuth\Contracts\BeginsDeviceRegistration;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Exceptions\TooManyPendingRequests;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Services\DeviceKeyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BeginDeviceRegistration implements BeginsDeviceRegistration
{
    public function __construct(private readonly DeviceKeyService $keys) {}

    public function begin(Request $request, array $input): array
    {
        /** @var array{name: string} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
        ])->validate();

        $limit = config('device-auth.max_pending_per_user', 3);

        $pending = Device::query()
            ->withStatus(DeviceStatus::Pending)
            ->where('requested_by', $request->user()?->getAuthIdentifier())
            ->count();

        if ($pending >= (is_numeric($limit) ? (int) $limit : 3)) {
            throw new TooManyPendingRequests;
        }

        return $this->keys->beginRegistration($request, trim($data['name']));
    }
}
