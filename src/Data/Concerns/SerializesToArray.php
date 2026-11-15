<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Data\Concerns;

trait SerializesToArray
{
    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
