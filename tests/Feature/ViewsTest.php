<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Tests\Feature;

use AbdulWahhabKhan\DeviceAuth\Contracts\Responses\VerifyViewResponse;
use AbdulWahhabKhan\DeviceAuth\Data\PageData;
use AbdulWahhabKhan\DeviceAuth\Data\VerifyPageData;
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;
use AbdulWahhabKhan\DeviceAuth\Enums\DeviceStatus;
use AbdulWahhabKhan\DeviceAuth\Http\Responses\ViewResponse;
use AbdulWahhabKhan\DeviceAuth\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

final class ViewsTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->app?->instance(VerifyViewResponse::class, new ViewResponse);

        parent::tearDown();
    }

    public function test_without_a_frontend_pages_answer_with_their_data_as_json(): void
    {
        $this->actingAs($this->user(['name' => 'Priya']))
            ->get(route('device-auth.verify'))
            ->assertOk()
            ->assertHeader('Accept-CH')
            ->assertJsonPath('user.name', 'Priya')
            ->assertJsonPath('urls.verify', route('device-auth.verify.store'));
    }

    public function test_a_closure_view_receives_the_typed_page_data(): void
    {
        DeviceAuth::verifyView(static fn (Request $request, PageData $data): string => $data instanceof VerifyPageData
            ? 'Hello '.($data->user['name'] ?? '')
            : 'wrong data');

        $this->actingAs($this->user(['name' => 'Priya']))
            ->get(route('device-auth.verify'))
            ->assertOk()
            ->assertSee('Hello Priya');
    }

    public function test_a_blade_view_receives_data_and_its_fields(): void
    {
        View::addNamespace('fixtures', __DIR__.'/../Fixtures/views');
        DeviceAuth::verifyView('fixtures::verify');

        $this->actingAs($this->user(['name' => 'Priya']))
            ->get(route('device-auth.verify'))
            ->assertOk()
            ->assertSee('Verify as Priya')
            ->assertSee(route('device-auth.verify.options'));
    }

    public function test_admin_pages_expose_paginated_devices_and_details(): void
    {
        $admin = $this->user(['is_superadmin' => true]);
        $current = $this->device(['status' => DeviceStatus::Active, 'name' => 'Admin PC']);
        $pending = $this->device(['name' => 'Ward tablet']);

        $this->onDevice($admin, $current)
            ->getJson(route('device-auth.admin.index', ['status' => 'pending']))
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('counts.pending', 1)
            ->assertJsonPath('counts.active', 1)
            ->assertJsonPath('devices.total', 1)
            ->assertJsonPath('devices.data.0.name', 'Ward tablet');

        $this->onDevice($admin, $current)
            ->getJson(route('device-auth.admin.show', $pending))
            ->assertOk()
            ->assertJsonPath('device.reference', $pending->reference())
            ->assertJsonPath('device.is_current', false)
            ->assertJsonPath('urls.approve', route('device-auth.admin.approve', $pending));
    }

    public function test_shared_state_describes_the_session(): void
    {
        $admin = $this->user(['is_superadmin' => true]);
        $current = $this->device(['status' => DeviceStatus::Active, 'name' => 'Admin PC']);
        $this->device();

        $this->onDevice($admin, $current)->get('/dashboard')->assertOk();

        $state = DeviceAuth::state(request())?->toArray();

        $this->assertNotNull($state);
        $this->assertTrue($state['isAdmin']);
        $this->assertSame(1, $state['pendingCount']);
        $this->assertSame('Admin PC', $state['device']['name'] ?? null);
        $this->assertSame(route('device-auth.admin.index'), $state['urls']['admin']);
    }
}
