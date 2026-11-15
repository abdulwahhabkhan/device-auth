<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Services;

/**
 * Turns an authenticator's AAGUID into a readable name ("Windows Hello").
 */
class AaguidDirectory
{
    /** @var array<string, string>|null */
    private ?array $names = null;

    public function name(?string $aaguid): ?string
    {
        if (! $aaguid) {
            return null;
        }

        return $this->names()[strtolower($aaguid)] ?? null;
    }

    /** @return array<string, string> */
    private function names(): array
    {
        if ($this->names !== null) {
            return $this->names;
        }

        $names = json_decode((string) file_get_contents(__DIR__.'/../../resources/data/aaguids.json'), true) ?: [];

        $custom = config('device-auth.aaguid_file');

        if ($custom && is_file($custom)) {
            foreach (json_decode((string) file_get_contents($custom), true) ?: [] as $id => $entry) {
                $name = is_array($entry) ? ($entry['name'] ?? null) : $entry;

                if (is_string($name)) {
                    $names[strtolower($id)] = $name;
                }
            }
        }

        return $this->names = $names;
    }
}
