<?php

namespace AbdulWahhabKhan\DeviceAuth\Tests;

use AbdulWahhabKhan\DeviceAuth\DeviceAuthServiceProvider;
use AbdulWahhabKhan\DeviceAuth\Tests\Support\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [InertiaServiceProvider::class, DeviceAuthServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('device-auth.rp_id', 'localhost');
        $app['config']->set('device-auth.home', '/dashboard');
        $app['config']->set('app.url', 'https://localhost');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_superadmin')->default(false);
            $table->timestamps();
        });
    }

    protected function defineRoutes($router): void
    {
        Route::middleware(['web', 'auth', 'device.verified'])->get('/dashboard', fn () => 'dashboard');
        Route::middleware(['web', 'auth', 'device.verified', 'device.confirm'])->post('/danger', fn () => 'done');
        Route::middleware('web')->get('/login', fn () => 'login')->name('login');
    }

    protected function user(array $attributes = []): User
    {
        return User::query()->create($attributes + [
            'name' => 'Priya Shah',
            'email' => uniqid().'@example.test',
            'password' => bcrypt('password'),
        ]);
    }
}
