<?php

namespace AbdulWahhabKhan\DeviceAuth\Tests\Support;

final class CborValue
{
    public function __construct(public readonly string $encoded) {}
}
