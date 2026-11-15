<?php

namespace AbdulWahhabKhan\DeviceAuth\Tests\Unit;

use AbdulWahhabKhan\DeviceAuth\Support\Base64Url;
use AbdulWahhabKhan\DeviceAuth\Tests\Support\SoftAuthenticator;
use AbdulWahhabKhan\DeviceAuth\WebAuthn\WebAuthnServer;
use lbuchs\WebAuthn\WebAuthnException;
use PHPUnit\Framework\TestCase;

class WebAuthnServerTest extends TestCase
{
    private WebAuthnServer $server;

    protected function setUp(): void
    {
        $this->server = new WebAuthnServer('Test App', 'localhost', 60, true);
    }

    private function register(SoftAuthenticator $device): \AbdulWahhabKhan\DeviceAuth\WebAuthn\RegisteredKey
    {
        $ceremony = $this->server->creationCeremony(random_bytes(16), 'Reception PC', 'Reception PC (device key)');
        $answer = $device->create($ceremony->options);

        return $this->server->verifyCreation(
            Base64Url::decode($answer['clientDataJSON']),
            Base64Url::decode($answer['attestationObject']),
            $ceremony->challenge,
        );
    }

    public function test_creation_options_require_a_built_in_authenticator_and_user_verification(): void
    {
        $options = $this->server->creationCeremony(random_bytes(16), 'x', 'x')->options['publicKey'];

        $this->assertSame('none', $options['attestation']);
        $this->assertSame('platform', $options['authenticatorSelection']['authenticatorAttachment']);
        $this->assertSame('required', $options['authenticatorSelection']['userVerification']);
        $this->assertSame('required', $options['authenticatorSelection']['residentKey']);
    }

    public function test_a_device_can_register_and_sign(): void
    {
        $device = new SoftAuthenticator;
        $key = $this->register($device);

        $this->assertSame($device->credentialId(), $key->credentialId);
        $this->assertSame('08987058-cadc-4b81-b6e1-30de50dcbe96', $key->aaguid);
        $this->assertFalse($key->backupEligible);

        $request = $this->server->requestCeremony([$key->credentialId]);
        $this->assertSame(['internal'], $request->options['publicKey']['allowCredentials'][0]['transports']);

        $signed = $device->get($request->options);

        $counter = $this->server->verifyAssertion(
            Base64Url::decode($signed['clientDataJSON']),
            Base64Url::decode($signed['authenticatorData']),
            Base64Url::decode($signed['signature']),
            $key->publicKey,
            $request->challenge,
            0,
        );

        $this->assertSame(0, $counter);
    }

    public function test_another_devices_signature_is_rejected(): void
    {
        $key = $this->register(new SoftAuthenticator);
        $request = $this->server->requestCeremony();
        $signed = (new SoftAuthenticator)->get($request->options);

        $this->expectException(WebAuthnException::class);

        $this->server->verifyAssertion(
            Base64Url::decode($signed['clientDataJSON']),
            Base64Url::decode($signed['authenticatorData']),
            Base64Url::decode($signed['signature']),
            $key->publicKey,
            $request->challenge,
            0,
        );
    }

    public function test_a_wrong_challenge_is_rejected(): void
    {
        $ceremony = $this->server->creationCeremony(random_bytes(16), 'x', 'x');
        $answer = (new SoftAuthenticator)->create($ceremony->options);

        $this->expectException(WebAuthnException::class);

        $this->server->verifyCreation(
            Base64Url::decode($answer['clientDataJSON']),
            Base64Url::decode($answer['attestationObject']),
            random_bytes(32),
        );
    }

    public function test_a_key_without_user_verification_is_rejected(): void
    {
        $this->expectException(WebAuthnException::class);

        $this->register(new SoftAuthenticator(userVerified: false));
    }

    public function test_a_foreign_origin_is_rejected(): void
    {
        $this->expectException(WebAuthnException::class);

        $this->register(new SoftAuthenticator(origin: 'https://evil.example'));
    }

    public function test_synced_passkeys_are_flagged(): void
    {
        $key = $this->register(new SoftAuthenticator(aaguid: str_repeat("\0", 16), backupEligible: true));

        $this->assertTrue($key->backupEligible);
        $this->assertTrue($key->backedUp);
        $this->assertNull($key->aaguid);
    }
}
