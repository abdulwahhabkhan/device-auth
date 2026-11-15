<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Tests\Support;

final readonly class CborValue
{
    public function __construct(public string $encoded) {}
}
