<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\WebAuthn;

use AbdulWahhabKhan\DeviceAuth\Support\Base64Url;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithms;
use Exception;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AndroidKeyAttestationStatementSupport;
use Webauthn\AttestationStatement\AppleAttestationStatementSupport;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AttestationStatement\TPMAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Framework-free wrapper around web-auth/webauthn-lib.
 *
 * Every ceremony requires user verification (fingerprint, face or PIN),
 * so a key on an unlocked-but-unattended device can't be used silently.
 *
 * Only the challenge is kept between the two halves of a ceremony; the
 * options are rebuilt deterministically when the answer is checked.
 * Library errors are rethrown as VerificationFailed so callers never
 * depend on the underlying library.
 */
final class WebAuthnServer
{
    private const TRANSPORTS_ALL = ['usb', 'nfc', 'ble', 'hybrid', 'internal'];

    private ?SerializerInterface $serializer = null;

    private ?AttestationStatementSupportManager $attestationSupport = null;

    /**
     * @param  positive-int  $timeout  seconds the browser prompt stays open
     */
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
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId),
            user: PublicKeyCredentialUserEntity::create($name, $userHandle, $displayName),
            challenge: $challenge,
            pubKeyCredParams: $this->algorithms(),
            authenticatorSelection: $this->authenticatorSelection(),
            // We don't verify manufacturers, so don't ask browsers for attestation
            // (avoids extra privacy prompts); the common formats are still accepted.
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            excludeCredentials: $this->descriptors($excludeCredentialIds),
            timeout: $this->timeout * 1000,
            hints: $this->hints(),
        );

        return new Ceremony(['publicKey' => $this->toBrowserArray($options)], $challenge);
    }

    /**
     * @throws VerificationFailed
     */
    public function verifyCreation(string $clientDataJSON, string $attestationObject, string $challenge): RegisteredKey
    {
        try {
            /** @var AuthenticatorAttestationResponse $response */
            $response = $this->denormalizer()->denormalize([
                'clientDataJSON' => Base64Url::encode($clientDataJSON),
                'attestationObject' => Base64Url::encode($attestationObject),
            ], AuthenticatorAttestationResponse::class, 'json');

            // Rebuild the options the browser was given; only the challenge varies.
            $options = PublicKeyCredentialCreationOptions::create(
                rp: PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId),
                user: PublicKeyCredentialUserEntity::create('device', "\0", 'device'),
                challenge: $challenge,
                pubKeyCredParams: $this->algorithms(),
                authenticatorSelection: $this->authenticatorSelection(),
                attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            );

            $record = AuthenticatorAttestationResponseValidator::create($this->steps()->creationCeremony())
                ->check($response, $options, $this->rpId);
        } catch (Exception $e) {
            throw VerificationFailed::from($e);
        }

        return new RegisteredKey(
            credentialId: Base64Url::encode($record->publicKeyCredentialId),
            publicKey: Base64Url::encode($record->credentialPublicKey),
            signCount: $record->counter,
            aaguid: $this->formatAaguid($record->aaguid),
            backupEligible: (bool) $record->backupEligible,
            backedUp: (bool) $record->backupStatus,
            attestationFormat: $response->attestationObject->attStmt->fmt,
        );
    }

    /**
     * Options for navigator.credentials.get().
     *
     * @param  list<string>  $allowCredentialIds  base64url ids; empty = let the device offer its keys
     */
    public function requestCeremony(array $allowCredentialIds = []): Ceremony
    {
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->rpId,
            allowCredentials: $this->descriptors($allowCredentialIds),
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: $this->timeout * 1000,
            hints: $this->hints(),
        );

        return new Ceremony(['publicKey' => $this->toBrowserArray($options)], $challenge);
    }

    /**
     * Verifies the signed challenge and returns the new signature counter.
     *
     * @param  string  $publicKey  as stored from RegisteredKey (base64url COSE key), or a PEM key from older versions
     * @param  bool|null  $backupEligible  stored flag; a key can't change it during its life
     *
     * @throws VerificationFailed
     */
    public function verifyAssertion(
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
        string $publicKey,
        string $challenge,
        int $previousSignCount,
        ?bool $backupEligible = null,
    ): int {
        try {
            /** @var AuthenticatorAssertionResponse $response */
            $response = $this->denormalizer()->denormalize([
                'clientDataJSON' => Base64Url::encode($clientDataJSON),
                'authenticatorData' => Base64Url::encode($authenticatorData),
                'signature' => Base64Url::encode($signature),
            ], AuthenticatorAssertionResponse::class, 'json');

            // The device handle isn't stored: a key is identified by its
            // credential id, which the caller already used to find it.
            $userHandle = $response->userHandle ?? '';

            $record = CredentialRecord::create(
                publicKeyCredentialId: "\0",
                type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                transports: [],
                attestationType: 'none',
                trustPath: EmptyTrustPath::create(),
                aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
                credentialPublicKey: CoseKey::fromStored($publicKey),
                userHandle: $userHandle,
                counter: $previousSignCount,
                backupEligible: $backupEligible,
            );

            $options = PublicKeyCredentialRequestOptions::create(
                challenge: $challenge,
                rpId: $this->rpId,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            );

            $record = AuthenticatorAssertionResponseValidator::create($this->steps()->requestCeremony())
                ->check($record, $response, $options, $this->rpId, $userHandle);
        } catch (Exception $e) {
            throw VerificationFailed::from($e);
        }

        return $record->counter;
    }

    private function authenticatorSelection(): AuthenticatorSelectionCriteria
    {
        return AuthenticatorSelectionCriteria::create(
            // platform = built into this device
            authenticatorAttachment: $this->platformOnly
                ? AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM
                : AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
            // require fingerprint / face / PIN
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            // discoverable key: shows up in the passkey picker
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
        );
    }

    /** @return list<PublicKeyCredentialParameters> */
    private function algorithms(): array
    {
        return [
            PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_ES256),
            PublicKeyCredentialParameters::createPk(Algorithms::COSE_ALGORITHM_RS256),
        ];
    }

    /**
     * @param  list<string>  $ids  base64url
     * @return list<PublicKeyCredentialDescriptor>
     */
    private function descriptors(array $ids): array
    {
        $transports = $this->platformOnly ? ['internal'] : self::TRANSPORTS_ALL;

        return array_map(fn (string $id): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            Base64Url::decode($id),
            $transports,
        ), $ids);
    }

    /** @return list<string> */
    private function hints(): array
    {
        return $this->platformOnly ? [PublicKeyCredentialOptions::HINT_CLIENT_DEVICE] : [];
    }

    private function steps(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory;

        // No fixed origin list: accept https on the RP id and its subdomains.
        $factory->setAllowedOrigins([], allowSubdomains: true);

        if ($this->rpId === 'localhost') {
            // Browsers treat http://localhost as secure, so allow it for local development.
            $factory->setSecuredRelyingPartyId(['localhost']);
        }

        $factory->setAttestationStatementSupportManager($this->attestationSupport());
        $factory->setAlgorithmManager($this->algorithmManager());

        return $factory;
    }

    private function attestationSupport(): AttestationStatementSupportManager
    {
        return $this->attestationSupport ??= new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport,
            new PackedAttestationStatementSupport($this->algorithmManager()),
            new FidoU2FAttestationStatementSupport,
            new AndroidKeyAttestationStatementSupport,
            new AppleAttestationStatementSupport,
            new TPMAttestationStatementSupport,
        ]);
    }

    private function algorithmManager(): Manager
    {
        return Manager::create()->add(ES256::create(), RS256::create());
    }

    private function serializer(): SerializerInterface
    {
        return $this->serializer ??= (new WebauthnSerializerFactory($this->attestationSupport()))->create();
    }

    private function denormalizer(): DenormalizerInterface
    {
        $serializer = $this->serializer();
        assert($serializer instanceof DenormalizerInterface);

        return $serializer;
    }

    /** @return array<string, mixed> */
    private function toBrowserArray(object $options): array
    {
        $serializer = $this->serializer();
        assert($serializer instanceof NormalizerInterface);

        $normalized = $serializer->normalize($options, 'json', [
            AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ]);

        return (array) $normalized;
    }

    private function formatAaguid(Uuid $aaguid): ?string
    {
        $binary = $aaguid->toBinary();

        return trim($binary, "\0") === '' ? null : $aaguid->toRfc4122();
    }
}
