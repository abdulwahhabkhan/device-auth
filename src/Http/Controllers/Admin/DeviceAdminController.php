<?php

namespace AbdulWahhabKhan\DeviceAuth\Http\Controllers\Admin;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceApproved;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceRejected;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceRevoked;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Models\DeviceLogin;
use AbdulWahhabKhan\DeviceAuth\Notifications\DeviceRequestReviewed;
use AbdulWahhabKhan\DeviceAuth\Services\AaguidDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The "authorized_keys" screen: review, approve, reject and revoke devices.
 */
class DeviceAdminController extends Controller
{
    private const FLASH = 'device_auth.flash';

    public function index(Request $request): Response
    {
        $status = DeviceStatus::tryFrom((string) $request->query('status', 'pending')) ?? DeviceStatus::Pending;
        $search = trim((string) $request->query('q', ''));

        $devices = Device::query()
            ->with('requester')
            ->withStatus($status)
            ->when($search !== '', function (Builder $query) use ($search) {
                $id = (int) preg_replace('/\D/', '', $search);

                $query->where(function (Builder $q) use ($search, $id) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('request_ip', 'like', "%{$search}%")
                        ->orWhere('last_ip', 'like', "%{$search}%");

                    if ($id > 0) {
                        $q->orWhere('id', $id);
                    }
                });
            })
            ->latest($status === DeviceStatus::Pending ? 'created_at' : 'updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Device $device) => $device->adminSummary());

        $counts = Device::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render(config('device-auth.pages.admin_index'), [
            'status' => $status->value,
            'search' => $search,
            'counts' => collect(DeviceStatus::cases())
                ->mapWithKeys(fn (DeviceStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'devices' => $devices,
            'urls' => [
                'index' => route('device-auth.admin.index'),
            ],
        ]);
    }

    public function show(Request $request, Device $device, AaguidDirectory $authenticators): Response
    {
        $device->load('requester', 'reviewer');

        return Inertia::render(config('device-auth.pages.admin_show'), [
            'device' => $device->adminDetails() + [
                'authenticator' => $authenticators->name($device->aaguid),
                'is_current' => DeviceAuth::currentDevice($request)?->is($device) ?? false,
            ],
            'logins' => $device->logins()
                ->with('user')
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (DeviceLogin $login) => [
                    'id' => $login->getKey(),
                    'user' => DeviceAuth::describeUser($login->user),
                    'ip' => $login->ip,
                    'purpose' => $login->purpose,
                    'created_at' => $login->created_at?->toIso8601String(),
                ]),
            'urls' => [
                'index' => route('device-auth.admin.index', ['status' => $device->status->value]),
                'approve' => route('device-auth.admin.approve', $device),
                'reject' => route('device-auth.admin.reject', $device),
                'revoke' => route('device-auth.admin.revoke', $device),
            ],
        ]);
    }

    public function approve(Request $request, Device $device): RedirectResponse
    {
        abort_if($device->status === DeviceStatus::Active, 409, 'This device is already approved.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->review($request, $device, DeviceStatus::Active, $data);

        DeviceApproved::dispatch($device, $request->user());

        return back()->with(self::FLASH, "{$device->name} approved. It can be used right away.");
    }

    public function reject(Request $request, Device $device): RedirectResponse
    {
        abort_unless($device->status === DeviceStatus::Pending, 409, 'Only pending requests can be rejected.');

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:500'],
        ], [
            'review_note.required' => 'Give a reason. The person who asked will see it.',
        ]);

        $this->review($request, $device, DeviceStatus::Rejected, $data);

        DeviceRejected::dispatch($device, $request->user());

        return back()->with(self::FLASH, "{$device->name} rejected.");
    }

    public function revoke(Request $request, Device $device): RedirectResponse
    {
        abort_unless($device->status === DeviceStatus::Active, 409, 'Only approved devices can be revoked.');

        if (DeviceAuth::currentDevice($request)?->is($device)) {
            return back()->withErrors([
                'review_note' => 'You can’t revoke the device you’re using. Revoke it from another approved device.',
            ]);
        }

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->review($request, $device, DeviceStatus::Revoked, $data);

        DeviceRevoked::dispatch($device, $request->user());

        return back()->with(self::FLASH, "{$device->name} revoked. It will be signed out on its next request.");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function review(Request $request, Device $device, DeviceStatus $status, array $data): void
    {
        $device->forceFill($data + [
            'status' => $status,
            'reviewed_by' => $request->user()->getAuthIdentifier(),
            'reviewed_at' => now(),
        ])->save();

        if (config('device-auth.notifications') && method_exists((object) $device->requester, 'notify')) {
            $device->requester->notify(new DeviceRequestReviewed($device));
        }
    }
}
