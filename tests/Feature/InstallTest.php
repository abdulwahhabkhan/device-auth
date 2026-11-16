<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Tests\Feature;

use AbdulWahhabKhan\DeviceAuth\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;

final class InstallTest extends TestCase
{
    public function test_the_published_action_stubs_match_the_package_actions(): void
    {
        $root = dirname(__DIR__, 2);
        $sources = glob($root.'/src/Actions/*.php') ?: [];

        $this->assertNotEmpty($sources);
        $this->assertCount(count($sources), glob($root.'/stubs/actions/*.php') ?: []);

        foreach ($sources as $source) {
            $expected = str_replace(
                'namespace AbdulWahhabKhan\\DeviceAuth\\Actions;',
                'namespace App\\Actions\\DeviceAuth;',
                (string) file_get_contents($source),
            );

            $this->assertSame(
                $expected,
                file_get_contents($root.'/stubs/actions/'.basename($source)),
                basename($source).' is out of date. Run `composer stubs:sync`.',
            );
        }
    }

    public function test_install_publishes_config_actions_and_provider(): void
    {
        $files = new Filesystem;
        $paths = [config_path('device-auth.php'), app_path('Actions/DeviceAuth'), app_path('Providers/DeviceAuthServiceProvider.php')];

        $this->artisan('device-auth:install')->assertSuccessful();

        try {
            $this->assertFileExists(config_path('device-auth.php'));
            $this->assertFileExists(app_path('Actions/DeviceAuth/ApproveDevice.php'));
            $this->assertFileExists(app_path('Providers/DeviceAuthServiceProvider.php'));
            $this->assertStringContainsString(
                'namespace App\\Actions\\DeviceAuth;',
                (string) file_get_contents(app_path('Actions/DeviceAuth/ApproveDevice.php')),
            );
        } finally {
            $files->delete([$paths[0], $paths[2]]);
            $files->deleteDirectory($paths[1]);
        }
    }

    public function test_translations_can_be_published_and_are_used(): void
    {
        $this->assertSame('Approved', __('device-auth::messages.status.active'));

        $this->artisan('vendor:publish', ['--tag' => 'device-auth-lang'])->assertSuccessful();

        try {
            $this->assertFileExists(lang_path('vendor/device-auth/en/messages.php'));
        } finally {
            (new Filesystem)->deleteDirectory(lang_path('vendor/device-auth'));
        }
    }
}
