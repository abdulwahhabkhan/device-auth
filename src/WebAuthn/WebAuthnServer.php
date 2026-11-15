<?php

namespace AbdulWahhabKhan\DeviceAuth\WebAuthn;

use AbdulWahhabKhan\DeviceAuth\Support\Base64Url;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;

/**
 * Framework-free wrapper around lbuchs/webauthn.
 *
 * Every ceremony requires user verification (fingerprint, face or PIN),
 * so a key on an unlocked-but-unattended device can't be used silently.
 */
final class WebAuthnServer
{
    private const FORMATS = ['none', 'packed', 'tpm', 'apple', 'android-key', 'android-safetynet', 'fido-u2f'];

    public function __construct(
        private readonly string $rpName,
        private readonly string $rpId,
        private readonly int $timeout = 60,
        private readonly bool $platformOnly = true,
    ) {}

    /**
     * Options for navigator.credentials.create().
     *
     * @param  string  $userHandle  random binary id that identifies the device, not a user
     * @param  list<string>  $excludeCredentialIds  base64url ids already registered
     */
    public function creationCeremony(string $userHandle, string $name, string $displayName, array $excludeCredentialIds = []): Ceremony
    {
        $lib = $this->lib();

        $args = $lib->getCreateArgs(
            $userHandle,
            $name,
            $displayName,
            $this->timeout,
            true,                                  // discoverable key: shows up in the passkey picker
            true,                                  // require fingerprint / face / PIN
            $this->platformOnly ? false : null,    // false = built into this device
            array_map(fn (string $id) => Base64Url::decode($id), $excludeCredentialIds),
        );

        // We don't verify manufacturers, so don't ask browsers for attestation
        // (avoids extra privacy prompts); any format is still accepted.
        $args->publicKey->attestation = 'none';

        if ($this->platformOnly) {
            $args->publicKey->hints = ['client-device'];
        }

        return new Ceremony(self::toArray($args), $lib->getChallenge()->getBinaryString());
    }

    public function verifyCreation(string $clientDataJSON, string $attestationObject, string $challenge): RegisteredKey
    {
        $data = $this->lib()->processCreate(
            $clientDataJSON,
            $attestationObject,
            new ByteBuffer($challenge),
            true,   // user verified
            true,   // user present
            false,  // no root certificates configured
            false,
        );

        $aaguid = $data->AAGUID instanceof ByteBuffer ? $data->AAGUID->getBinaryString() : (string) $data->AAGUID;

        return new RegisteredKey(
            credentialId: Base64Url::encode((string) $data->credentialId),
            publicKey: (string) $data->credentialPublicKey,
            signCount: (int) ($data->signatureCounter ?? 0),
            aaguid: self::formatAaguid($aaguid),
            backupEligible: (bool) ($data->isBackupEligible ?? false),
            backedUp: (bool) ($data->isBackedUp ?? false),
            attestationFormat: (string) $data->attestationFormat,
        );
    }

    /**
     * Options for navigator.credentials.get().
     *
     * @param  list<string>  $allowCredentialIds  base64url ids; empty = let the device offer its keys
     */
    public function requestCeremony(array $allowCredentialIds = []): Ceremony
    {
        $lib = $this->lib();
        $external = ! $this->platformOnly;

        $args = $lib->getGetArgs(
            array_map(fn (string $id) => Base64Url::decode($id), $allowCredentialIds),
            $this->timeout,
            $external,  // usb
            $external,  // nfc
            $external,  // ble
            $external,  // hybrid ("use a phone" QR code)
            true,       // internal
            true,       // require fingerprint / face / PIN
        );

        if ($this->platformOnly) {
            $args->publicKey->hints = ['client-device'];
        }

        return new Ceremony(self::toArray($args), $lib->getChallenge()->getBinaryString());
    }

    /**
     * Verifies the signed challenge and returns the new signature counter.
     */
    public function verifyAssertion(
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
        string $publicKeyPem,
        string $challenge,
        int $previousSignCount,
    ): int {
        $lib = $this->lib();

        $lib->processGet(
            $clientDataJSON,
            $authenticatorData,
            $signature,
            $publicKeyPem,
            new ByteBuffer($challenge),
            $previousSignCount,
            true,   // user verified
            true,   // user present
        );

        return $lib->getSignatureCounter() ?? $previousSignCount;
    }

    private function lib(): WebAuthn
    {
        return new WebAuthn($this->rpName, $this->rpId, self::FORMATS, true);
    }

    /** @return array<string, mixed> */
    private static function toArray(object $args): array
    {
        // ByteBuffer serialises itself as base64url (useBase64UrlEncoding = true).
        return json_decode(json_encode($args, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }

    private static function formatAaguid(string $binary): ?string
    {
        if (strlen($binary) !== 16 || trim($binary, "\0") === '') {
            return null;
        }

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($binary), 4));
    }
}
