<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Services;

use AbdulWahhabKhan\DeviceAuth\Enums\CeremonyPurpose;
use AbdulWahhabKhan\DeviceAuth\Exceptions\ChallengeExpired;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceMismatch;
use AbdulWahhabKhan\DeviceAuth\Exceptions\UnknownDeviceKey;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Support\Base64Url;
use AbdulWahhabKhan\DeviceAuth\WebAuthn\RegisteredKey;
use AbdulWahhabKhan\DeviceAuth\WebAuthn\VerificationFailed;
use AbdulWahhabKhan\DeviceAuth\WebAuthn\WebAuthnServer;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Laravel glue around WebAuthnServer: keeps challenges in the session and
 * looks keys up in the devices table. Stateless, safe to share.
 *
 * @phpstan-type Assertion array{id: string, clientDataJSON: string, authenticatorData: string, signature: string}
 * @phpstan-type Attestation array{clientDataJSON: string, attestationObject: string}
 */
class DeviceKeyService
{
    private const string CHALLENGE_KEY = 'device_auth.challenge.';

    public function server(Request $request): WebAuthnServer
    {
        $rpName = config('device-auth.rp_name') ?: config('app.name', 'Laravel');
        $rpId = config('device-auth.rp_id') ?: $request->getHost();

        return new WebAuthnServer(
            is_string($rpName) ? $rpName : 'Laravel',
            is_string($rpId) ? $rpId : $request->getHost(),
            $this->timeout(),
            (bool) config('device-auth.platform_authenticators_only', true),
        );
    }

    /**
     * Options for creating a new key on this device.
     *
     * @return array<string, mixed>
     */
    public function beginRegistration(Request $request, string $deviceName): array
    {
        $ceremony = $this->server($request)->creationCeremony(
            random_bytes(16),                    // device handle, deliberately not the user id
            $deviceName,
            $deviceName.' (device key)',
        );

        $this->rememberChallenge($request, CeremonyPurpose::Register, $ceremony->challenge);

        return $ceremony->options;
    }

    /**
     * @param  Attestation  $credential
     *
     * @throws DeviceAuthException
     */
    public function finishRegistration(Request $request, array $credential): RegisteredKey
    {
        $challenge = $this->pullChallenge($request, CeremonyPurpose::Register);

        try {
            return $this->server($request)->verifyCreation(
                Base64Url::decode($credential['clientDataJSON']),
                Base64Url::decode($credential['attestationObject']),
                $challenge,
            );
        } catch (VerificationFailed|InvalidArgumentException $e) {
            throw new DeviceAuthException(__('device-auth::messages.key_not_created', ['reason' => $e->getMessage()]), 0, $e);
        }
    }

    /**
     * Options for asking this device to sign a challenge.
     * With $only, the browser goes straight to that device's key.
     *
     * @return array<string, mixed>
     */
    public function beginVerification(Request $request, CeremonyPurpose $purpose, ?Device $only = null): array
    {
        $ceremony = $this->server($request)->requestCeremony($only instanceof Device ? [$only->credential_id] : []);

        $this->rememberChallenge($request, $purpose, $ceremony->challenge);

        return $ceremony->options;
    }

    /**
     * Checks the signature and returns the device the key belongs to,
     * whatever its status. The caller decides what pending/rejected means.
     *
     * @param  Assertion  $assertion
     *
     * @throws DeviceAuthException
     */
    public function finishVerification(Request $request, CeremonyPurpose $purpose, array $assertion, ?Device $only = null): Device
    {
        $challenge = $this->pullChallenge($request, $purpose);

        $device = Device::query()->where('credential_id', $assertion['id'])->first();

        if (! $device instanceof Device) {
            throw new UnknownDeviceKey;
        }

        if ($only instanceof Device && ! $device->is($only)) {
            throw new DeviceMismatch(__('device-auth::messages.confirm_same_device'));
        }

        try {
            $counter = $this->server($request)->verifyAssertion(
                Base64Url::decode($assertion['clientDataJSON']),
                Base64Url::decode($assertion['authenticatorData']),
                Base64Url::decode($assertion['signature']),
                $device->public_key,
                $challenge,
                $device->sign_count,
                $device->backup_eligible,
            );
        } catch (VerificationFailed|InvalidArgumentException $e) {
            throw new DeviceAuthException(__('device-auth::messages.not_verified', ['reason' => $e->getMessage()]), 0, $e);
        }

        $device->forceFill([
            'sign_count' => $counter,
            'last_ip' => $request->ip(),
            'last_seen_at' => now(),
        ])->save();

        return $device;
    }

    /** @return positive-int */
    private function timeout(): int
    {
        $timeout = config('device-auth.timeout', 60);

        return is_numeric($timeout) ? max(1, (int) $timeout) : 60;
    }

    private function rememberChallenge(Request $request, CeremonyPurpose $purpose, string $challenge): void
    {
        $request->session()->put(self::CHALLENGE_KEY.$purpose->value, [
            'value' => base64_encode($challenge),
            // Prompt timeout plus a little slack for slow networks.
            'expires_at' => now()->getTimestamp() + $this->timeout() + 30,
        ]);
    }

    private function pullChallenge(Request $request, CeremonyPurpose $purpose): string
    {
        $stored = $request->session()->pull(self::CHALLENGE_KEY.$purpose->value);

        if (! is_array($stored) || ! is_string($stored['value'] ?? null) || ! is_int($stored['expires_at'] ?? null)
            || $stored['expires_at'] < now()->getTimestamp()) {
            throw new ChallengeExpired;
        }

        return (string) base64_decode($stored['value'], true);
    }
}
