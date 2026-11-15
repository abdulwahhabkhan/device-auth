<?php

namespace AbdulWahhabKhan\DeviceAuth\WebAuthn;

/**
 * Options to hand to navigator.credentials.create()/get(), plus the
 * challenge the server must remember to check the answer.
 */
final class Ceremony
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public readonly array $options,
        public readonly string $challenge, // binary
    ) {}
}
