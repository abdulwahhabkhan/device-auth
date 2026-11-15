<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Console;

use App\Providers\DeviceAuthServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Publishes everything an app customizes, like `fortify:install`:
 * config, migrations, the action classes and a service provider that
 * registers them.
 */
#[AsCommand(name: 'device-auth:install')]
class InstallCommand extends Command
{
    protected $signature = 'device-auth:install {--force : Overwrite files that were already published}';

    protected $description = 'Publish the device-auth config, migrations, actions and service provider';

    public function handle(): int
    {
        foreach (['device-auth-config', 'device-auth-migrations', 'device-auth-actions', 'device-auth-provider'] as $tag) {
            $this->callSilently('vendor:publish', ['--tag' => $tag, '--force' => (bool) $this->option('force')]);
        }

        $this->registerProvider();

        $this->components->info('Device auth scaffolding installed.');
        $this->components->bulletList([
            'Install a frontend: <comment>composer require abdulwahhabkhan/device-auth-react</comment> or <comment>abdulwahhabkhan/device-auth-livewire</comment>',
            'Run <comment>php artisan migrate</comment>',
            'Protect routes with <comment>[\'auth\', \'device.verified\']</comment>, and sensitive ones with <comment>device.confirm</comment>',
            'Customize behaviour in <comment>app/Actions/DeviceAuth</comment>',
            'Approve the first device from the terminal: <comment>php artisan device-auth:approve {id}</comment>',
        ]);

        return self::SUCCESS;
    }

    private function registerProvider(): void
    {
        $provider = DeviceAuthServiceProvider::class;
        $bootstrap = $this->laravel->bootstrapPath('providers.php');

        if (is_file($bootstrap)) {
            ServiceProvider::addProviderToBootstrapFile($provider, $bootstrap);

            return;
        }

        $this->components->warn('Register '.$provider.' in your application\'s providers.');
    }
}
