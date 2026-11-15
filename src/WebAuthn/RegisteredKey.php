<?php

namespace AbdulWahhabKhan\DeviceAuth\WebAuthn;

/**
 * The public half of a newly created device key, ready to be stored.
 */
final class RegisteredKey
{
    public function __construct(
        public readonly string $credentialId,   // base64url
        public readonly string $publicKey,      // PEM
        public readonly int $signCount,
        public readonly ?string $aaguid,        // xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx or null
        public readonly bool $backupEligible,   // true = synced passkey (iCloud, Google…)
        public readonly bool $backedUp,
        public readonly string $attestationFormat,
    ) {}

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'credential_id' => $this->credentialId,
            'public_key' => $this->publicKey,
            'sign_count' => $this->signCount,
            'aaguid' => $this->aaguid,
            'backup_eligible' => $this->backupEligible,
            'backed_up' => $this->backedUp,
        ];
    }
}
