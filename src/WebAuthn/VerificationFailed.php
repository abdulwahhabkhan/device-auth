<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\WebAuthn;

use RuntimeException;
use Throwable;

/**
 * A WebAuthn answer was malformed or didn't check out (wrong challenge,
 * origin, signature, missing user verification…).
 */
final class VerificationFailed extends RuntimeException
{
    public static function from(Throwable $previous): self
    {
        return new self($previous->getMessage(), 0, $previous);
    }
}
