<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\WebAuthn;

/**
 * The public half of a newly created device key, ready to be stored.
 */
final readonly class RegisteredKey
{
    public function __construct(
        public string $credentialId,   // base64url
        public string $publicKey,      // COSE key, base64url
        public int $signCount,
        public ?string $aaguid,        // xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx or null
        public bool $backupEligible,   // true = synced passkey (iCloud, Google…)
        public bool $backedUp,
        public string $attestationFormat,
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
