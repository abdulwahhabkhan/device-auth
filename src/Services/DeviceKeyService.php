<?php

namespace AbdulWahhabKhan\DeviceAuth\Services;

use AbdulWahhabKhan\DeviceAuth\Exceptions\ChallengeExpired;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceAuthException;
use AbdulWahhabKhan\DeviceAuth\Exceptions\DeviceMismatch;
use AbdulWahhabKhan\DeviceAuth\Exceptions\UnknownDeviceKey;
use AbdulWahhabKhan\DeviceAuth\Models\Device;
use AbdulWahhabKhan\DeviceAuth\Support\Base64Url;
use AbdulWahhabKhan\DeviceAuth\WebAuthn\RegisteredKey;
use AbdulWahhabKhan\DeviceAuth\WebAuthn\WebAuthnServer;
use Illuminate\Http\Request;
use lbuchs\WebAuthn\WebAuthnException;

/**
 * Laravel glue around WebAuthnServer: keeps challenges in the session and
 * looks keys up in the devices table.
 */
class DeviceKeyService
{
    private const CHALLENGE_KEY = 'device_auth.challenge.';

    public function __construct(private readonly Request $request) {}

    public function server(): WebAuthnServer
    {
        return new WebAuthnServer(
            (string) config('device-auth.rp_name', config('app.name', 'Laravel')),
            (string) (config('device-auth.rp_id') ?: $this->request->getHost()),
            (int) config('device-auth.timeout', 60),
            (bool) config('device-auth.platform_authenticators_only', true),
        );
    }

    /**
     * Options for creating a new key on this device.
     *
     * @return array<string, mixed>
     */
    public function beginRegistration(string $deviceName): array
    {
        $ceremony = $this->server()->creationCeremony(
            random_bytes(16),                    // device handle, deliberately not the user id
            $deviceName,
            $deviceName.' (device key)',
        );

        $this->rememberChallenge('register', $ceremony->challenge);

        return $ceremony->options;
    }

    /**
     * @param  array{clientDataJSON: string, attestationObject: string}  $credential
     */
    public function finishRegistration(array $credential): RegisteredKey
    {
        $challenge = $this->pullChallenge('register');

        try {
            return $this->server()->verifyCreation(
                Base64Url::decode($credential['clientDataJSON']),
                Base64Url::decode($credential['attestationObject']),
                $challenge,
            );
        } catch (WebAuthnException|\InvalidArgumentException $e) {
            throw new DeviceAuthException('The device key could not be created: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Options for asking this device to sign a challenge.
     * With $only, the browser goes straight to that device's key.
     *
     * @return array<string, mixed>
     */
    public function beginVerification(string $purpose, ?Device $only = null): array
    {
        $ceremony = $this->server()->requestCeremony($only ? [$only->credential_id] : []);

        $this->rememberChallenge($purpose, $ceremony->challenge);

        return $ceremony->options;
    }

    /**
     * Checks the signature and returns the device the key belongs to,
     * whatever its status. The caller decides what pending/rejected means.
     *
     * @param  array{id: string, clientDataJSON: string, authenticatorData: string, signature: string}  $assertion
     */
    public function finishVerification(string $purpose, array $assertion, ?Device $only = null): Device
    {
        $challenge = $this->pullChallenge($purpose);

        /** @var Device|null $device */
        $device = Device::query()->where('credential_id', $assertion['id'])->first();

        if (! $device) {
            throw new UnknownDeviceKey;
        }

        if ($only && ! $device->is($only)) {
            throw new DeviceMismatch('Confirm with the same device you signed in on.');
        }

        try {
            $counter = $this->server()->verifyAssertion(
                Base64Url::decode($assertion['clientDataJSON']),
                Base64Url::decode($assertion['authenticatorData']),
                Base64Url::decode($assertion['signature']),
                $device->public_key,
                $challenge,
                $device->sign_count,
            );
        } catch (WebAuthnException|\InvalidArgumentException $e) {
            throw new DeviceAuthException('The device could not be verified: '.$e->getMessage(), 0, $e);
        }

        $device->forceFill([
            'sign_count' => $counter,
            'last_ip' => $this->request->ip(),
            'last_seen_at' => now(),
        ])->save();

        return $device;
    }

    private function rememberChallenge(string $purpose, string $challenge): void
    {
        $this->request->session()->put(self::CHALLENGE_KEY.$purpose, [
            'value' => base64_encode($challenge),
            // Prompt timeout plus a little slack for slow networks.
            'expires_at' => now()->getTimestamp() + (int) config('device-auth.timeout', 60) + 30,
        ]);
    }

    private function pullChallenge(string $purpose): string
    {
        $stored = $this->request->session()->pull(self::CHALLENGE_KEY.$purpose);

        if (! is_array($stored) || ($stored['expires_at'] ?? 0) < now()->getTimestamp()) {
            throw new ChallengeExpired;
        }

        return base64_decode((string) $stored['value']);
    }
}
