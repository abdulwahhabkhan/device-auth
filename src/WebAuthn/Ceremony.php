<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\WebAuthn;

/**
 * Options to hand to navigator.credentials.create()/get(), plus the
 * challenge the server must remember to check the answer.
 */
final readonly class Ceremony
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public array $options,
        public string $challenge, // binary
    ) {}
}
