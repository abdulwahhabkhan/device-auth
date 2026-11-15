<?php

declare(strict_types=1);

namespace AbdulWahhabKhan\DeviceAuth\Services;

use AbdulWahhabKhan\DeviceAuth\Models\Device;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\OperatingSystem;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Describes the device behind a request so an administrator can judge it.
 */
class DeviceMetadata
{
    /** Keys accepted from the browser (everything else is dropped). */
    public const CLIENT_KEYS = ['timezone', 'language', 'screen', 'touchPoints', 'platform', 'platformVersion', 'model'];

    /**
     * @param  array<string, mixed>  $client  values collected by the browser
     * @return array<string, mixed>
     */
    public function describe(Request $request, array $client = []): array
    {
        $parsed = $this->parse($request);

        return [
            'request_ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'os_family' => $parsed['os_family'],
            'device_type' => $parsed['device_type'],
            'device_model' => $parsed['device_model'],
            'client_meta' => array_map(
                fn ($value): ?string => is_scalar($value) ? mb_substr((string) $value, 0, 100) : null,
                Arr::only($client, self::CLIENT_KEYS),
            ),
        ];
    }

    /**
     * @return array{browser: string|null, os: string|null, os_family: string|null, device_type: string|null, device_model: string|null}
     */
    public function parse(Request $request): array
    {
        $detector = new DeviceDetector(
            (string) $request->userAgent(),
            ClientHints::factory($request->server->all()),
        );
        $detector->parse();

        $known = fn ($value): ?string => is_string($value) && $value !== '' && $value !== 'UNK' ? $value : null;

        $osName = $known($detector->getOs('name'));
        $browser = $known($detector->getClient('name'));

        return [
            'browser' => $browser ? trim($browser.' '.($known($detector->getClient('version')) ?? '')) : null,
            'os' => $osName ? trim($osName.' '.($known($detector->getOs('version')) ?? '')) : null,
            'os_family' => $osName ? OperatingSystem::getOsFamily($osName) : null,
            'device_type' => $known($detector->getDeviceName()),
            'device_model' => trim($detector->getBrandName().' '.$detector->getModel()) ?: null,
        ];
    }

    /**
     * Is this request coming from the same kind of device the key was approved for?
     * Compares only OS family and device type, so browser/OS updates don't lock anyone out.
     */
    public function matchesDevice(Request $request, Device $device): bool
    {
        $current = $this->parse($request);

        foreach (['os_family', 'device_type'] as $field) {
            $expected = $device->{$field};

            if ($expected && $current[$field] && $expected !== $current[$field]) {
                return false;
            }
        }

        return true;
    }
}
