# Changelog

All notable changes to `abdulwahhabkhan/device-auth` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-11-15

### Changed (breaking)
- The package is now **backend only**. The Inertia React pages moved to `abdulwahhabkhan/device-auth-react`, and a Livewire frontend is available as `abdulwahhabkhan/device-auth-livewire`. `inertiajs/inertia-laravel` is no longer required.
- The `pages` config key moved to `config/device-auth-react.php`.
- The `device-auth-react` publish tag moved to the React package.
- WebAuthn checks now use `web-auth/webauthn-lib` instead of `lbuchs/webauthn`. Existing PEM keys keep working.
- Admin approve, reject and revoke on a device in the wrong state now answer `409` JSON (`status: conflict`), or redirect back with an error on `device`, instead of aborting.
- `DeviceKeyService` methods take the `Request` as their first argument.
- Event `$actor` and `$user` properties are typed `?Authenticatable`.

### Added
- Fortify-style action classes behind contracts (`ApprovesDevice`, `VerifiesDevice`, …), with `DeviceAuth::*Using()` to swap them and publishable copies (`device-auth-actions`, `device-auth-provider`).
- `device-auth:install` publishes the config, actions and provider, and registers the provider.
- View callbacks `DeviceAuth::verifyView()`, `confirmView()`, `deviceIndexView()` and `deviceShowView()`. Typed page data objects (`VerifyPageData`, …). Pages answer JSON when no view is registered.
- Response contracts for every endpoint.
- `DeviceAuth::state()`, `ignoreRoutes()`, `ignoreMigrations()`.
- Translations for every message (`device-auth-lang`), plus publishable routes (`device-auth-routes`).
- `declare(strict_types=1)` everywhere, PHPStan level 7, Rector and Pint in CI.

### Fixed
- Package tests now run migrations, and confirmation timing uses `now()`, so tests can travel in time.

## [0.0.1] - 2026-11-15

- First release.
