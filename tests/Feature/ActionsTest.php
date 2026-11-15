<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Tests\Feature;

use AbdulWahhabKhan\DeviceAuth\Actions\ApproveDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\ApprovesDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\ListsDevices;
use AbdulWahhabKhan\DeviceAuth\Contracts\RejectsDevice;
use AbdulWahhabKhan\DeviceAuth\Contracts\RevokesDevice;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceApproved;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceRejected;
use AbdulWahhabKhan\DeviceAuth\Events\DeviceRevoked;
use AbdulWahhabKhan\DeviceAuth\Exceptions\InvalidDeviceState;
use AbdulWahhabKhan\DeviceAuth\Notifications\DeviceRequestReviewed;
use AbdulWahhabKhan\DeviceAuth\Tests\Fixtures\ApproveWithTicket;
use AbdulWahhabKhan\DeviceAuth\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

final class ActionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Event::fake([DeviceApproved::class, DeviceRejected::class, DeviceRevoked::class]);
    }

    public function test_approving_activates_the_device_and_tells_the_requester(): void
    {
        $requester = $this->user();
        $admin = $this->user(['is_superadmin' => true]);
        $device = $this->device(['requested_by' => $requester->id]);

        resolve(ApprovesDevice::class)->approve($device, $admin, ['name' => ' Ward tablet ', 'review_note' => 'OK']);

        $device->refresh();
        $this->assertSame(DeviceStatus::Active, $device->status);
        $this->assertSame('Ward tablet', $device->name);
        $this->assertSame($admin->id, (int) $device->reviewed_by);
        Event::assertDispatched(DeviceApproved::class);
        Notification::assertSentTo($requester, DeviceRequestReviewed::class);
    }

    public function test_approving_an_active_device_is_a_conflict(): void
    {
        $this->expectException(InvalidDeviceState::class);

        resolve(ApprovesDevice::class)->approve($this->device(['status' => DeviceStatus::Active]), null, ['name' => 'PC']);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $device = $this->device();

        try {
            resolve(RejectsDevice::class)->reject($device, null, []);
            $this->fail('Rejecting without a reason should fail validation.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('review_note', $e->errors());
        }

        resolve(RejectsDevice::class)->reject($device, null, ['review_note' => 'Unknown laptop']);

        $this->assertSame(DeviceStatus::Rejected, $device->refresh()->status);
        Event::assertDispatched(DeviceRejected::class);
    }

    public function test_the_reviewer_cannot_revoke_the_device_they_are_using(): void
    {
        $device = $this->device(['status' => DeviceStatus::Active]);

        $this->expectException(ValidationException::class);

        resolve(RevokesDevice::class)->revoke($device, null, [], $device);
    }

    public function test_revoking_an_active_device(): void
    {
        $device = $this->device(['status' => DeviceStatus::Active]);

        resolve(RevokesDevice::class)->revoke($device, null, ['review_note' => 'Lost'], $this->device(['status' => DeviceStatus::Active]));

        $this->assertSame(DeviceStatus::Revoked, $device->refresh()->status);
        Event::assertDispatched(DeviceRevoked::class);
    }

    public function test_listing_filters_by_status_and_search(): void
    {
        $this->device(['name' => 'Reception PC']);
        $this->device(['name' => 'Ward tablet']);
        $this->device(['name' => 'Old laptop', 'status' => DeviceStatus::Revoked]);

        $lister = resolve(ListsDevices::class);

        $this->assertSame(2, $lister->list(DeviceStatus::Pending)->total());
        $this->assertSame(1, $lister->list(DeviceStatus::Pending, 'ward')->total());
        $this->assertSame(1, $lister->list(DeviceStatus::Revoked)->total());
    }

    public function test_an_app_can_swap_in_its_own_action(): void
    {
        DeviceAuth::approveDevicesUsing(ApproveWithTicket::class);

        $this->assertInstanceOf(ApproveWithTicket::class, resolve(ApprovesDevice::class));

        $admin = $this->user(['is_superadmin' => true]);
        $current = $this->device(['status' => DeviceStatus::Active, 'name' => 'Admin PC']);
        $pending = $this->device();

        $this->onDevice($admin, $current)
            ->post(route('device-auth.admin.approve', $pending), ['name' => 'Ward tablet'])
            ->assertSessionHasErrors('ticket');

        $this->onDevice($admin, $current)
            ->post(route('device-auth.admin.approve', $pending), ['name' => 'Ward tablet', 'ticket' => 'IT-42'])
            ->assertSessionHas(DeviceAuth::SESSION_FLASH);

        $this->assertSame(DeviceStatus::Active, $pending->refresh()->status);

        DeviceAuth::approveDevicesUsing(ApproveDevice::class);
    }

    public function test_review_endpoints_answer_json_with_the_right_status(): void
    {
        $admin = $this->user(['is_superadmin' => true]);
        $current = $this->device(['status' => DeviceStatus::Active, 'name' => 'Admin PC']);
        $active = $this->device(['status' => DeviceStatus::Active, 'name' => 'Ward tablet']);

        $this->onDevice($admin, $current)
            ->postJson(route('device-auth.admin.approve', $active), ['name' => 'Ward tablet'])
            ->assertStatus(409)
            ->assertJsonPath('status', 'conflict');

        $this->onDevice($admin, $current)
            ->postJson(route('device-auth.admin.revoke', $active))
            ->assertOk()
            ->assertJsonPath('status', 'revoked');
    }

    public function test_console_commands_use_the_actions(): void
    {
        $device = $this->device();

        $this->artisan('device-auth:approve', ['device' => $device->reference(), '--name' => 'First PC'])->assertSuccessful();
        $this->assertSame(DeviceStatus::Active, $device->refresh()->status);
        $this->assertSame('First PC', $device->name);

        $this->artisan('device-auth:approve', ['device' => (string) $device->id])->assertFailed();

        $this->artisan('device-auth:revoke', ['device' => (string) $device->id, '--reason' => 'Stolen'])->assertSuccessful();
        $this->assertSame('Stolen', $device->refresh()->review_note);

        $this->artisan('device-auth:list', ['--status' => 'revoked'])->assertSuccessful();
        $this->artisan('device-auth:list', ['--status' => 'nope'])->assertFailed();
    }
}
