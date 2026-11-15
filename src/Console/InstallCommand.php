<?php

namespace AbdulWahhabKhan\DeviceAuth\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'device-auth:install {--force : Overwrite files that were already published}';

    protected $description = 'Publish the device-auth config and React pages';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'device-auth-config', '--force' => $this->option('force')]);
        $this->call('vendor:publish', ['--tag' => 'device-auth-react', '--force' => $this->option('force')]);

        $this->components->info('Device auth installed.');
        $this->components->bulletList([
            'Run <comment>php artisan migrate</comment>',
            'Protect routes with <comment>[\'auth\', \'device.verified\']</comment>, and sensitive ones with <comment>device.confirm</comment>',
            'Mark your administrators (column <comment>'.config('device-auth.admin_attribute').'</comment>) or call DeviceAuth::authorizeAdminsUsing()',
            'Rebuild assets: <comment>npm run build</comment>',
            'Approve the first device from the terminal: <comment>php artisan device-auth:approve {id}</comment>',
        ]);

        return self::SUCCESS;
    }
}
