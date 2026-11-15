<?php

namespace AbdulWahhabKhan\DeviceAuth\Tests\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = ['is_superadmin' => 'boolean'];
}
