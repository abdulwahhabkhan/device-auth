<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable device authentication
    |--------------------------------------------------------------------------
    |
    | When disabled, the "device.verified" and "device.confirm" middleware let
    | every request through. Use this during rollout: deploy disabled,
    | register and approve devices, then switch it on.
    |
    */

    'enabled' => env('DEVICE_AUTH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Relying party (WebAuthn)
    |--------------------------------------------------------------------------
    |
    | rp_id must be the domain the browser sees (no scheme, no port), e.g.
    | "app.example.com". Leave null to use the current request host.
    | Keys are bound to this domain: changing it invalidates every key.
    |
    */

    'rp_name' => env('DEVICE_AUTH_RP_NAME', env('APP_NAME', 'Laravel')),

    'rp_id' => env('DEVICE_AUTH_RP_ID'),

    // Seconds the browser prompt stays open.
    'timeout' => 60,

    /*
    |--------------------------------------------------------------------------
    | Confirmation window
    |--------------------------------------------------------------------------
    |
    | Routes behind "device.confirm" require the device to have been verified
    | within this many seconds (like Laravel's password.confirm).
    |
    */

    'confirm_timeout' => env('DEVICE_AUTH_CONFIRM_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Requests
    |--------------------------------------------------------------------------
    */

    // How many pending requests one user may have open at the same time.
    'max_pending_per_user' => 3,

    // Shown to users whose device is pending, rejected or revoked.
    'admin_contact' => env('DEVICE_AUTH_ADMIN_CONTACT'),

    // Reject a key when it is used from a different OS family or device type
    // than the one recorded when it was approved.
    'enforce_platform_match' => true,

    // Only allow keys stored in the device itself (Windows Hello, Touch ID,
    // Android screen lock), not USB security keys or another phone.
    'platform_authenticators_only' => true,

    /*
    |--------------------------------------------------------------------------
    | Administrators
    |--------------------------------------------------------------------------
    |
    | Users for whom this attribute is truthy may review devices. For custom
    | rules call DeviceAuth::authorizeAdminsUsing(fn ($user) => ...) in a
    | service provider instead.
    |
    */

    'admin_attribute' => 'is_superadmin',

    'user_model' => null, // null = auth.providers.users.model

    // Column type of your users' primary key: "int", "uuid" or "ulid".
    'user_key_type' => 'int',

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */

    'routes' => [
        'enabled' => true,
        'prefix' => 'device',
        'admin_prefix' => 'admin/devices',
        'middleware' => ['web', 'auth'],
        'throttle' => 20, // attempts per minute per user
    ],

    // Where to go after a successful verification when no intended URL exists.
    'home' => '/dashboard',

    // Route name for logging out (shown on the verification screen).
    'logout_route' => 'logout',

    /*
    |--------------------------------------------------------------------------
    | Inertia page components
    |--------------------------------------------------------------------------
    |
    | Change these if you publish the pages somewhere else.
    |
    */

    'pages' => [
        'verify' => 'device-auth/verify',
        'confirm' => 'device-auth/confirm',
        'admin_index' => 'device-auth/admin/index',
        'admin_show' => 'device-auth/admin/show',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Channels for request/approve/reject notifications. Add "database" if your
    | app has a notifications table. Set to [] to rely on events only.
    |
    */

    'notifications' => ['mail'],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

    'tables' => [
        'devices' => 'device_auth_devices',
        'logins' => 'device_auth_logins',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authenticator names
    |--------------------------------------------------------------------------
    |
    | Optional path to a JSON file mapping AAGUIDs to names, in the format of
    | the community "passkey-authenticator-aaguids" list. A short built-in
    | list covers the common platform authenticators.
    |
    */

    'aaguid_file' => null,

];
