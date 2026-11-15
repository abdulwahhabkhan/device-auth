<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Exceptions;

use RuntimeException;

/**
 * Base class for every expected device-auth failure. Each subclass carries
 * a machine-readable reason and the HTTP status the API should answer with.
 */
class DeviceAuthException extends RuntimeException
{
    /** Machine-readable reason sent to the frontend. */
    public function reason(): string
    {
        return 'error';
    }

    public function status(): int
    {
        return 422;
    }

    /**
     * JSON body for API responses.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->reason(),
            'message' => $this->getMessage(),
        ];
    }
}
