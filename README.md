# abdulwahhabkhan/device-auth

Only approved devices can use your Laravel application. Each device proves itself with a **passkey** (WebAuthn), not a password and not an SSH key.

Each computer, phone or tablet creates its own passkey inside its secure hardware (Windows Hello / TPM, Apple Secure Enclave, Android keystore). The private key never leaves the device. The application stores only the public key, and an administrator approves it. No static IP or VPN is needed.

If you know SSH, the approval model is the same idea. Nothing here uses SSH or `ssh-keygen`; the comparison only explains the flow:

| SSH (for comparison) | This package (passkeys / WebAuthn) |
|---|---|
| You run `ssh-keygen` on your computer | The user clicks **Request access for this device**; the browser creates a passkey on the device |
| Public key added to `authorized_keys` | Public key stored as **pending**, administrator clicks **Approve** |
| Server sends a challenge, client signs it | App sends a challenge, device signs it after fingerprint, face or PIN |
| Remove the line from `authorized_keys` | **Revoke**: the device is signed out on its next request |

It works in two places:

1. **After the password (2FA step)**: `device.verified` middleware. A signed-in user can't reach the app until this device proves it holds an approved key.
2. **Before sensitive actions**: `device.confirm` middleware, the device version of Laravel's `password.confirm`. The same device must confirm again if the last confirmation is older than 10 minutes.

Keys belong to the **device**, not the user: any user may sign in on any approved device, and every use is logged.

## Packages

This is the **backend**: WebAuthn, models, middleware, actions, routes and JSON endpoints. Pair it with a frontend, or render the pages yourself.

| Package | What it adds |
|---|---|
| `abdulwahhabkhan/device-auth` | This package. Backend only, no frontend dependency. |
| [`abdulwahhabkhan/device-auth-react`](https://github.com/abdulwahhabkhan/device-auth-react) | Inertia + React pages (verify, confirm, admin) and helpers. |
| [`abdulwahhabkhan/device-auth-livewire`](https://github.com/abdulwahhabkhan/device-auth-livewire) | Livewire + Alpine pages, plain Tailwind. |

## Requirements

PHP 8.3+ and Laravel 13. The site must be served over **https** (or `localhost` during development).

### Dependencies

| Package | Used for |
|---|---|
| `web-auth/webauthn-lib` ^5.3 | Checks WebAuthn registrations and signatures. It's the same library `laravel/passkeys` uses, so apps with both install it only once. |
| `matomo/device-detector` ^6.4 | Turns the user agent and client hints into the browser, OS and device type shown to administrators. |
| `laravel/framework` ^13 | Laravel itself. The package uses framework helpers (`config()`, `route()`, `__()`, `event()`…) and event traits, so the full framework is required rather than individual `illuminate/*` components. |
| `ext-openssl` | Signature checks. |

Nothing here depends on a frontend stack: Inertia, React and Livewire come only with the frontend package you choose.

The authenticator names (Windows Hello, iCloud Keychain…) come from `resources/data/aaguids.json`, which ships with the package, so nothing is looked up over the network.

**Upgrading from a version that used `lbuchs/webauthn`:** new keys are stored as COSE keys instead of PEM. Devices registered earlier keep working without a migration, because their PEM keys are converted when they sign in. Such a device stops working only if it was registered with an Ed25519 key, which built-in authenticators don't use.

## Installation

```bash
composer require abdulwahhabkhan/device-auth
php artisan device-auth:install    # config, app/Actions/DeviceAuth, App\Providers\DeviceAuthServiceProvider
php artisan migrate

# then a frontend
composer require abdulwahhabkhan/device-auth-react      # or
composer require abdulwahhabkhan/device-auth-livewire
```

`device-auth:install` works like `fortify:install`. It publishes the action classes to `app/Actions/DeviceAuth` and registers `App\Providers\DeviceAuthServiceProvider` in `bootstrap/providers.php`. That provider points every step at your copies.

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

By default users whose `is_superadmin` attribute is true can review devices (`admin_attribute` in the config). For other rules, in your `DeviceAuthServiceProvider`:

```php
DeviceAuth::authorizeAdminsUsing(fn ($user) => $user->hasRole('it-admin'));
DeviceAuth::resolveAdminsUsing(fn () => User::role('it-admin')->get()); // who gets "new request" mails
```

The admin screens are at `/admin/devices`. They need the `manage-device-auth` gate and a verified device. Approve, reject and revoke also need a fresh device confirmation.

### The first device

The admin screens are only reachable from an approved device, so approve the first one from the terminal:

```bash
php artisan device-auth:list --status=pending
php artisan device-auth:approve 1
```

During rollout you can set `DEVICE_AUTH_ENABLED=false`, register the devices, approve them, then switch it on.

## Customizing

### Actions

Every step is a small class behind a contract, in the style of Laravel Fortify. Edit your published copy, or point a contract at any class:

| Contract | Default | Does |
|---|---|---|
| `BeginsDeviceRegistration` | `BeginDeviceRegistration` | Validates the device name, enforces the pending limit, returns WebAuthn creation options |
| `RequestsDeviceAccess` | `RequestDeviceAccess` | Stores the new key as pending, records device details, notifies admins |
| `BeginsDeviceVerification` | `BeginDeviceVerification` | WebAuthn request options for verify or confirm |
| `VerifiesDevice` | `VerifyDevice` | Checks the signature and platform, marks the session verified, logs the sign-in |
| `ConfirmsDevice` | `ConfirmDevice` | Re-confirms the session's own device before sensitive actions |
| `ApprovesDevice` | `ApproveDevice` | Approves (also used by `device-auth:approve`) |
| `RejectsDevice` | `RejectDevice` | Rejects with a reason |
| `RevokesDevice` | `RevokeDevice` | Revokes (also used by `device-auth:revoke`) |
| `ListsDevices` | `ListDevices` | The admin list: status, search, pagination |

```php
// app/Providers/DeviceAuthServiceProvider.php
DeviceAuth::approveDevicesUsing(ApproveDevice::class);   // App\Actions\DeviceAuth\ApproveDevice
```

Actions validate their own input (`ValidationException`) and throw `DeviceAuthException` subclasses for expected failures (`UnknownDeviceKey`, `DeviceNotApproved`, `DeviceMismatch`, `ChallengeExpired`, `TooManyPendingRequests`, `InvalidDeviceState`). Each exception carries a machine-readable `reason()` and an HTTP `status()`.

### Views

Pages are rendered through view callbacks, like `Fortify::loginView()`. The frontend packages register them for you. To render a page yourself:

```php
use AbdulWahhabKhan\DeviceAuth\Data\VerifyPageData;

DeviceAuth::verifyView('auth.verify-device');   // Blade: gets $data plus each field ($user, $contact, $urls)
DeviceAuth::verifyView(fn (Request $request, VerifyPageData $data) => Inertia::render('Verify', $data->toArray())); // needs inertiajs/inertia-laravel in your app
DeviceAuth::confirmView(...);
DeviceAuth::deviceIndexView(...);
DeviceAuth::deviceShowView(...);
```

Each page gets a typed, read-only data object (`VerifyPageData`, `ConfirmPageData`, `DeviceIndexPageData`, `DeviceShowPageData`). Without a registered view, a page answers with that data as JSON, which suits a separate SPA. `DeviceAuth::state()` returns the shared session state (current device, admin flag, pending count, confirm URLs) for layouts.

### Responses

Bind your own implementation of any contract in `AbdulWahhabKhan\DeviceAuth\Contracts\Responses` to change what an endpoint returns: `DeviceVerifiedResponse`, `DeviceConfirmedResponse`, `DeviceAccessRequestedResponse`, `DeviceReviewedResponse`, `FailedDeviceAuthResponse`, and the page responses `VerifyViewResponse`, `ConfirmViewResponse`, `DeviceIndexViewResponse`, `DeviceShowViewResponse`.

### Publishable resources

| Tag | Publishes |
|---|---|
| `device-auth-config` | `config/device-auth.php` |
| `device-auth-migrations` | the migration (only needed with `DeviceAuth::ignoreMigrations()`) |
| `device-auth-actions` | `app/Actions/DeviceAuth/*` |
| `device-auth-provider` | `app/Providers/DeviceAuthServiceProvider.php` |
| `device-auth-routes` | `routes/device-auth.php` (call `DeviceAuth::ignoreRoutes()` and load your copy) |
| `device-auth-lang` | `lang/vendor/device-auth` (every message and mail line) |

`DeviceAuth::ignoreMigrations()` stops the package from loading its own migration.

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

- Keys must be created by the device's **built-in** authenticator (no USB keys, no “use a phone” QR code), and every use needs fingerprint, face or PIN. Set `platform_authenticators_only` to `false` to also allow security keys and phones.
- With `enforce_platform_match` (default on), a key approved for a Windows computer is refused from an iPhone, and so on (OS family and device type; browser and OS updates are fine).
- **Synced passkeys:** on iPhone, iPad, Mac and most Android phones, passkeys sync through iCloud Keychain or Google Password Manager (or a password manager such as 1Password or Bitwarden), so another device signed in to the same Apple or Google account could use the key. The admin screen flags these. For strict per-device control, use a separate Apple/Google account on those devices. Windows Hello keys stay in the computer's TPM.
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

Other options: `platform_authenticators_only`, `enforce_platform_match`, `admin_attribute`, notification channels, pending requests per user, route prefixes and middleware, table names, user key type (`int`, `uuid`, `ulid`).

## Events

`DeviceAccessRequested`, `DeviceApproved`, `DeviceRejected`, `DeviceRevoked`, `DeviceVerified` (with `purpose` `verify` or `confirm`), all in `AbdulWahhabKhan\DeviceAuth\Events`.

## Artisan

```bash
php artisan device-auth:install
php artisan device-auth:list [--status=pending]
php artisan device-auth:approve {id|DEV-00001} [--name=]
php artisan device-auth:revoke {id|DEV-00001} [--reason=]
```

## Development

```bash
composer install
composer test          # pint --test, rector --dry-run, phpstan (level 7), phpunit
composer lint          # format with Pint
composer refactor      # apply Rector
composer stubs:sync    # copy src/Actions to stubs/actions after editing an action
```

`tests/Support/SoftAuthenticator.php` is a software authenticator that creates real P-256 keys and browser-shaped responses, so the whole flow can be tested without a browser. You can use it in your app's tests too.

## License

MIT
