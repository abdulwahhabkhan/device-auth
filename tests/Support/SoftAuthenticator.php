<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Tests\Support;

use AbdulWahhabKhan\DeviceAuth\Support\Base64Url;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * A software stand-in for a device's built-in authenticator (TPM, Secure
 * Enclave…). It creates a real P-256 key pair and produces the same
 * clientDataJSON / attestationObject / signature a browser would send,
 * so the whole server side can be tested without a browser.
 */
final class SoftAuthenticator
{
    private readonly OpenSSLAsymmetricKey $key;

    private readonly string $credentialId;

    private int $counter = 0;

    public function __construct(
        private readonly string $rpId = 'localhost',
        private readonly string $origin = 'https://localhost',
        private readonly string $aaguid = "\x08\x98\x70\x58\xca\xdc\x4b\x81\xb6\xe1\x30\xde\x50\xdc\xbe\x96", // Windows Hello
        private readonly bool $backupEligible = false,
        private readonly bool $userVerified = true,
    ) {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);

        if ($key === false) {
            throw new RuntimeException('Could not create EC key: '.openssl_error_string());
        }

        $this->key = $key;
        $this->credentialId = random_bytes(32);
    }

    public function credentialId(): string
    {
        return Base64Url::encode($this->credentialId);
    }

    /** The public key as PEM, the format older versions of the package stored. */
    public function publicKeyPem(): string
    {
        return openssl_pkey_get_details($this->key)['key'];
    }

    /**
     * Answer for navigator.credentials.create(), base64url-encoded like the browser helper sends it.
     *
     * @param  array<string, mixed>  $options  the "publicKey" options from the server
     * @return array{clientDataJSON: string, attestationObject: string}
     */
    public function create(array $options): array
    {
        $clientData = $this->clientData('webauthn.create', $options['publicKey']['challenge']);

        $details = openssl_pkey_get_details($this->key);
        $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        // COSE_Key for ES256
        $coseKey = Cbor::map([[1, 2], [3, -7], [-1, 1], [-2, Cbor::bytes($x)], [-3, Cbor::bytes($y)]]);

        $authData = hash('sha256', $this->rpId, true)
            .chr($this->flags() | 0x40) // AT: attested credential data included
            .pack('N', $this->counter)
            .$this->aaguid
            .pack('n', strlen($this->credentialId))
            .$this->credentialId
            .$coseKey;

        $attestationObject = Cbor::map([
            ['fmt', 'none'],
            ['attStmt', Cbor::raw(Cbor::map([]))],
            ['authData', Cbor::bytes($authData)],
        ]);

        return [
            'clientDataJSON' => Base64Url::encode($clientData),
            'attestationObject' => Base64Url::encode($attestationObject),
        ];
    }

    /**
     * Answer for navigator.credentials.get().
     *
     * @param  array<string, mixed>  $options
     * @return array{id: string, clientDataJSON: string, authenticatorData: string, signature: string}
     */
    public function get(array $options): array
    {
        $clientData = $this->clientData('webauthn.get', $options['publicKey']['challenge']);

        $authData = hash('sha256', $this->rpId, true)
            .chr($this->flags())
            .pack('N', $this->counter);

        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return [
            'id' => $this->credentialId(),
            'clientDataJSON' => Base64Url::encode($clientData),
            'authenticatorData' => Base64Url::encode($authData),
            'signature' => Base64Url::encode($signature),
        ];
    }

    private function flags(): int
    {
        $flags = 0x01; // UP

        if ($this->userVerified) {
            $flags |= 0x04;
        }

        if ($this->backupEligible) {
            $flags |= 0x08 | 0x10; // BE + BS
        }

        return $flags;
    }

    private function clientData(string $type, string $challenge): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => $challenge,
            'origin' => $this->origin,
            'crossOrigin' => false,
        ], JSON_UNESCAPED_SLASHES);
    }
}
