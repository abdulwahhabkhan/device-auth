<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\WebAuthn;

use AbdulWahhabKhan\DeviceAuth\Support\Base64Url;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;
use Cose\Algorithms;
use InvalidArgumentException;

/**
 * Turns a stored public key back into the binary COSE key the verifier needs.
 *
 * Keys are stored as base64url COSE. Devices registered before the switch to
 * web-auth/webauthn-lib have a PEM key instead; those are converted on the fly.
 */
final class CoseKey
{
    public static function fromStored(string $stored): string
    {
        return str_starts_with(ltrim($stored), '-----BEGIN')
            ? self::fromPem($stored)
            : Base64Url::decode($stored);
    }

    public static function fromPem(string $pem): string
    {
        $key = openssl_pkey_get_public($pem);
        $details = $key ? openssl_pkey_get_details($key) : false;

        if (! is_array($details)) {
            throw new InvalidArgumentException('The stored public key is not a valid PEM key.');
        }

        return match ($details['type']) {
            OPENSSL_KEYTYPE_EC => self::ec2($details['ec'] ?? []),
            OPENSSL_KEYTYPE_RSA => self::rsa($details['rsa'] ?? []),
            default => throw new InvalidArgumentException('Unsupported public key type.'),
        };
    }

    /** @param  array<string, string>  $ec */
    private static function ec2(array $ec): string
    {
        if (($ec['curve_name'] ?? null) !== 'prime256v1') {
            throw new InvalidArgumentException('Unsupported elliptic curve.');
        }

        return (string) MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))                     // kty: EC2
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(Algorithms::COSE_ALGORITHM_ES256))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))                    // crv: P-256
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create(str_pad($ec['x'], 32, "\0", STR_PAD_LEFT)))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create(str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)));
    }

    /** @param  array<string, string>  $rsa */
    private static function rsa(array $rsa): string
    {
        return (string) MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(3))                     // kty: RSA
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(Algorithms::COSE_ALGORITHM_RS256))
            ->add(NegativeIntegerObject::create(-1), ByteStringObject::create($rsa['n']))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($rsa['e']));
    }
}
