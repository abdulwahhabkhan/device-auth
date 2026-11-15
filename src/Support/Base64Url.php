<?php

namespace AbdulWahhabKhan\DeviceAuth\Support;

final class Base64Url
{
    public static function encode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function decode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);

        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64url value.');
        }

        return $decoded;
    }
}
