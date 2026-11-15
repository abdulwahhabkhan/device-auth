<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Tests\Feature;

use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Notifications\NewDeviceRequest;
use AbdulWahhabKhan\DeviceAuth\Tests\Support\SoftAuthenticator;
use AbdulWahhabKhan\DeviceAuth\Tests\TestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

class DeviceFlowTest extends TestCase
{
    private const WINDOWS_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->withHeader('User-Agent', self::WINDOWS_CHROME);
    }

    private function requestAccess(SoftAuthenticator $key, string $name = 'Reception PC'): Device
    {
        $options = $this->postJson(route('device-auth.request.options'), ['name' => $name])->assertOk()->json();

        $this->postJson(route('device-auth.request.store'), [
            'name' => $name,
            'note' => 'Front desk',
            'client' => ['timezone' => 'Europe/London', 'language' => 'en-GB'],
            'credential' => $key->create($options),
        ])->assertCreated()->assertJsonPath('status', 'pending');

        return Device::query()->latest('id')->firstOrFail();
    }

    private function verify(SoftAuthenticator $key): TestResponse
    {
        $options = $this->postJson(route('device-auth.verify.options'))->assertOk()->json();

        return $this->postJson(route('device-auth.verify.store'), $key->get($options));
    }

    public function test_signed_in_users_without_a_verified_device_are_sent_to_verification(): void
    {
        $this->actingAs($this->user())
            ->get('/dashboard')
            ->assertRedirect(route('device-auth.verify'));
    }

    public function test_a_new_device_is_stored_as_pending_with_its_details(): void
    {
        $admin = $this->user(['is_superadmin' => true]);
        $this->actingAs($this->user());

        $device = $this->requestAccess(new SoftAuthenticator);

        $this->assertSame(DeviceStatus::Pending, $device->status);
        $this->assertSame('Reception PC', $device->name);
        $this->assertSame('Front desk', $device->request_note);
        $this->assertSame('Windows', $device->os_family);
        $this->assertSame('desktop', $device->device_type);
        $this->assertSame('Europe/London', $device->client_meta['timezone']);
        Notification::assertSentTo($admin, NewDeviceRequest::class);
    }

    public function test_unknown_pending_and_approved_keys_get_the_right_answer(): void
    {
        $this->actingAs($this->user());
        $key = new SoftAuthenticator;

        $this->verify($key)->assertNotFound()->assertJsonPath('status', 'not_registered');

        $device = $this->requestAccess($key);

        $this->verify($key)->assertForbidden()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('device.reference', $device->reference());

        $device->update(['status' => DeviceStatus::Active]);

        $this->verify($key)->assertOk()->assertJsonPath('status', 'active');
        $this->get('/dashboard')->assertOk();
        $this->assertDatabaseHas(config('device-auth.tables.logins'), ['device_id' => $device->id, 'purpose' => 'verify']);
    }

    public function test_revoking_a_device_signs_it_out_on_the_next_request(): void
    {
        $this->actingAs($this->user());
        $key = new SoftAuthenticator;
        $device = $this->requestAccess($key);
        $device->update(['status' => DeviceStatus::Active]);
        $this->verify($key)->assertOk();

        $device->update(['status' => DeviceStatus::Revoked, 'review_note' => 'Lost laptop']);

        $this->get('/dashboard')->assertRedirect(route('device-auth.verify'));
        $this->verify($key)->assertForbidden()
            ->assertJsonPath('status', 'revoked')
            ->assertJsonPath('device.review_note', 'Lost laptop');
    }

    public function test_a_key_approved_for_a_computer_is_refused_from_a_phone(): void
    {
        $this->actingAs($this->user());
        $key = new SoftAuthenticator;
        $this->requestAccess($key)->update(['status' => DeviceStatus::Active]);

        $this->withHeader('User-Agent', self::IPHONE);

        $this->verify($key)->assertStatus(422)->assertJsonPath('status', 'mismatch');
    }

    public function test_pending_requests_per_user_are_limited(): void
    {
        config(['device-auth.max_pending_per_user' => 1]);
        $this->actingAs($this->user());
        $this->requestAccess(new SoftAuthenticator);

        $this->postJson(route('device-auth.request.options'), ['name' => 'Second'])->assertStatus(429);
    }

    public function test_sensitive_actions_need_a_recent_confirmation_from_the_same_device(): void
    {
        $this->actingAs($this->user());
        $key = new SoftAuthenticator;
        $this->requestAccess($key)->update(['status' => DeviceStatus::Active]);
        $this->verify($key)->assertOk();

        $this->post('/danger')->assertOk(); // verification counts as a fresh confirmation

        $this->travel(config('device-auth.confirm_timeout') + 1)->seconds();
        $this->postJson('/danger')->assertStatus(423);

        // Another approved device can't confirm for this session.
        $other = new SoftAuthenticator;
        $this->requestAccess($other, 'Other PC')->update(['status' => DeviceStatus::Active]);
        $options = $this->postJson(route('device-auth.confirm.options'))->json();
        $this->postJson(route('device-auth.confirm.store'), $other->get($options))->assertStatus(422);

        $options = $this->postJson(route('device-auth.confirm.options'))->json();
        $this->postJson(route('device-auth.confirm.store'), $key->get($options))->assertOk();
        $this->post('/danger')->assertOk();
    }

    public function test_only_admins_on_a_verified_device_can_approve(): void
    {
        $requester = $this->user();
        $this->actingAs($requester);
        $pending = $this->requestAccess(new SoftAuthenticator, 'Ward tablet');

        // Not an admin.
        $adminKey = new SoftAuthenticator;
        $this->requestAccess($adminKey, 'My PC')->update(['status' => DeviceStatus::Active]);
        $this->verify($adminKey)->assertOk();
        $this->post(route('device-auth.admin.approve', $pending), ['name' => 'Ward tablet'])->assertForbidden();

        // Admin.
        $admin = $this->user(['is_superadmin' => true]);
        $this->actingAs($admin);
        $this->session([
            DeviceAuth::SESSION_DEVICE => Device::query()->where('name', 'My PC')->value('id'),
            DeviceAuth::SESSION_USER => (string) $admin->id,
            DeviceAuth::SESSION_CONFIRMED_AT => time(),
        ]);

        $this->post(route('device-auth.admin.approve', $pending), ['name' => 'Ward tablet 3'])->assertRedirect();

        $pending->refresh();
        $this->assertSame(DeviceStatus::Active, $pending->status);
        $this->assertSame('Ward tablet 3', $pending->name);
        $this->assertSame($admin->id, (int) $pending->reviewed_by);
    }

    public function test_disabled_device_auth_lets_everyone_through(): void
    {
        config(['device-auth.enabled' => false]);

        $this->actingAs($this->user())->get('/dashboard')->assertOk();
    }
}
