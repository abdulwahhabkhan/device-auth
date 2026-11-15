# abdulwahhabkhan/device-auth

Only approved devices can use your Laravel application, the way only approved SSH keys can log in to a server.

Each computer, phone or tablet creates its own key pair inside its secure hardware (Windows Hello / TPM, Apple Secure Enclave, Android keystore) using WebAuthn. The private key never leaves the device. The application stores only the public key, and an administrator approves it, like adding a line to `authorized_keys`. No static IP or VPN is needed.

| SSH | This package |
|---|---|
| `ssh-keygen` on your computer | “Request access for this device” creates a key on the device |
| Public key added to `authorized_keys` | Public key stored as **pending**, administrator clicks **Approve** |
| Server sends a challenge, client signs it | App sends a challenge, device signs it after fingerprint, face or PIN |
| Remove the line from `authorized_keys` | **Revoke**: the device is signed out on its next request |

It works in two places:

1. **After the password (2FA step)**: `device.verified` middleware. A signed-in user can't reach the app until this device proves it holds an approved key.
2. **Before sensitive actions**: `device.confirm` middleware, the device version of Laravel's `password.confirm`. The same device must confirm again if the last confirmation is older than 10 minutes.

Keys belong to the **device**, not the user: any user may sign in on any approved device, and every use is logged.

## Requirements

PHP 8.2+, Laravel 11, 12 or 13, Inertia (Laravel adapter 2 or 3) with React and Tailwind CSS. The site must be served over **https** (or `localhost` during development).

### Dependencies

Composer installs these with the package:

| Package | Used for |
|---|---|
| `web-auth/webauthn-lib` ^5.3 | Checks WebAuthn registrations and signatures. It's the same library `laravel/passkeys` uses, so apps with both install it only once. |
| `matomo/device-detector` ^6.4 | Turns the user agent and client hints into the browser, OS and device type shown to administrators. |
| `inertiajs/inertia-laravel` ^2.0 or ^3.0 | Renders the verify, confirm and admin pages. |
| `illuminate/*` ^11, ^12 or ^13 | Laravel support, database, HTTP and notifications. |
| `ext-openssl` | Signature checks. |

The React pages need `react` and `@inertiajs/react`, which an Inertia React app already has. They use the browser's own WebAuthn API, so no npm WebAuthn package is needed. The authenticator names (Windows Hello, iCloud Keychain…) come from `resources/data/aaguids.json`, which ships with the package, so nothing is looked up over the network.

**Upgrading from a version that used `lbuchs/webauthn`:** new keys are stored as COSE keys instead of PEM. Devices registered earlier keep working without a migration, because their PEM keys are converted when they sign in. Such a device stops working only if it was registered with an Ed25519 key, which built-in authenticators don't use.

## Installation

```bash
composer require abdulwahhabkhan/device-auth
php artisan device-auth:install   # publishes config/device-auth.php and the React pages
php artisan migrate
npm run build
```

The React files are published to:

```
resources/js/pages/device-auth/       verify, confirm, admin/index, admin/show
resources/js/device-auth/             shared components and helpers
```

They use plain Tailwind utilities (neutral palette with `dark:` variants), React and `@inertiajs/react` only. Edit them freely after publishing.

### Inertia page resolution

Pages are named `device-auth/verify`, `device-auth/confirm`, `device-auth/admin/index` and `device-auth/admin/show`. If your app resolves pages from `resources/js/pages` (the Laravel starter kits do), nothing else is needed. Otherwise change `pages` in `config/device-auth.php`.

The verify and confirm pages draw their own full-screen layout. If your `createInertiaApp` assigns a default layout, exclude them:

```tsx
layout: (name) => {
    if (name === 'device-auth/verify' || name === 'device-auth/confirm') return null;
    // ...your existing rules
},
```

### Protect routes

```php
Route::middleware(['auth', 'device.verified'])->group(function () {
    Route::get('/dashboard', ...);

    Route::middleware('device.confirm')->group(function () {
        Route::delete('/invoices/{invoice}', ...);
    });
});
```

### Administrators

By default users whose `is_superadmin` attribute is true can review devices (`admin_attribute` in the config). For other rules:

```php
// AppServiceProvider::boot()
use AbdulWahhabKhan\DeviceAuth\DeviceAuth;

DeviceAuth::authorizeAdminsUsing(fn ($user) => $user->hasRole('it-admin'));
DeviceAuth::resolveAdminsUsing(fn () => User::role('it-admin')->get()); // who gets "new request" mails
```

The admin screens are at `/admin/devices` and need the `manage-device-auth` gate, a verified device, and a fresh device confirmation for approve, reject and revoke.

### The first device

The admin screens are only reachable from an approved device, so approve the first one from the terminal:

```bash
php artisan device-auth:list --status=pending
php artisan device-auth:approve 1
```

During rollout you can set `DEVICE_AUTH_ENABLED=false`, register the devices, approve them, then switch it on.

## Frontend helpers

```tsx
import { ConfirmsDevice, useDeviceConfirmation, DeviceConfirmPrompt, useDeviceAuth } from '@/device-auth';

// Ask the device to confirm before an action
<ConfirmsDevice onConfirmed={() => router.delete(url)}>
    <button>Delete</button>
</ConfirmsDevice>

// Or programmatically
const { confirm, dialog } = useDeviceConfirmation();
if (await confirm()) { /* ... */ }   // render {dialog}

// Mount once in your app layout: opens the dialog when the server
// bounced an action with "confirm required"
<DeviceConfirmPrompt />

// Shared props on every page: current device, pending count for admins, URLs
const deviceAuth = useDeviceAuth();
```

## What the user sees

| Situation | Screen |
|---|---|
| Key unknown, or no key on this device | “No approved key found” with **Request access for this device** |
| Request sent / still pending | “Waiting for approval” with the reference (e.g. `DEV-00042`) to give the administrator, and the contact from `DEVICE_AUTH_ADMIN_CONTACT` |
| Rejected | The administrator's reason and **Request again** |
| Revoked | The reason and who to contact |
| Key approved for another kind of device | “This key belongs to another device” |
| Approved | Straight into the app |

Browsers deliberately don't tell a website whether a key exists, so “no key on this device” and “prompt closed” look the same; the screen offers both **Try again** and **Request access**. Pending, rejected and revoked statuses are only revealed after the device has proved it holds the key.

## What the administrator sees

For each request: who asked and why, device type, OS, browser, model, screen, time zone, language, request IP, user agent, the authenticator (Windows Hello, iCloud Keychain, Google Password Manager…), and whether the key is **synced** through a password manager. Approved devices show their last use and a log of recent sign-ins and confirmations.

## Keeping the key on the device

- Keys must be created by the device's **built-in** authenticator (no USB keys, no “use a phone” QR code), and every use needs fingerprint, face or PIN.
- With `enforce_platform_match` (default on), a key approved for a Windows computer is refused from an iPhone, and so on (OS family and device type; browser and OS updates are fine).
- **Synced passkeys:** on iPhone, iPad, Mac and most Android phones, passkeys sync through iCloud Keychain or Google Password Manager, so another device signed in to the same Apple or Google account could use the key. The admin screen flags these. For strict per-device control, use a separate Apple/Google account on those devices. Windows Hello keys stay in the computer's TPM.
- The browser's passkey picker may also list a user's personal sign-in passkeys for the same site. Device keys are labelled “*name* (device key)”.

## Configuration

See `config/device-auth.php`. Common environment variables:

```dotenv
DEVICE_AUTH_ENABLED=true
DEVICE_AUTH_RP_ID=app.example.com      # the domain browsers see; empty = request host
DEVICE_AUTH_ADMIN_CONTACT="IT desk, ext. 204"
DEVICE_AUTH_CONFIRM_TIMEOUT=600
```

Changing `DEVICE_AUTH_RP_ID` (the domain) invalidates every key, because browsers bind keys to it.

Other options: notification channels, pending requests per user, route prefixes and middleware, table names, user key type (`int`, `uuid`, `ulid`), page names.

## Events

`DeviceAccessRequested`, `DeviceApproved`, `DeviceRejected`, `DeviceRevoked`, `DeviceVerified` (with `purpose` `verify` or `confirm`), all in `AbdulWahhabKhan\DeviceAuth\Events`.

## Artisan

```bash
php artisan device-auth:install
php artisan device-auth:list [--status=pending]
php artisan device-auth:approve {id|DEV-00001} [--name=]
php artisan device-auth:revoke {id|DEV-00001} [--reason=]
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Support/SoftAuthenticator.php` is a software authenticator that creates real P-256 keys and browser-shaped responses, so the whole flow can be tested without a browser. You can use it in your app's tests too.
