<?php

namespace AbdulWahhabKhan\DeviceAuth\Tests\Support;

/**
 * Just enough CBOR encoding to build WebAuthn test fixtures.
 */
final class Cbor
{
    /** @param  list<array{0: int|string, 1: mixed}>  $pairs */
    public static function map(array $pairs): string
    {
        $out = self::head(5, count($pairs));

        foreach ($pairs as [$key, $value]) {
            $out .= self::encode($key).self::encode($value);
        }

        return $out;
    }

    public static function bytes(string $binary): CborValue
    {
        return new CborValue(self::head(2, strlen($binary)).$binary);
    }

    /** Wrap an already-encoded item (e.g. a nested map). */
    public static function raw(string $encoded): CborValue
    {
        return new CborValue($encoded);
    }

    private static function encode(mixed $value): string
    {
        return match (true) {
            $value instanceof CborValue => $value->encoded,
            is_int($value) && $value >= 0 => self::head(0, $value),
            is_int($value) => self::head(1, -1 - $value),
            is_string($value) => self::head(3, strlen($value)).$value,
            default => throw new \InvalidArgumentException('Unsupported CBOR value'),
        };
    }

    private static function head(int $major, int $length): string
    {
        $major <<= 5;

        return match (true) {
            $length < 24 => chr($major | $length),
            $length < 0x100 => chr($major | 24).chr($length),
            $length < 0x10000 => chr($major | 25).pack('n', $length),
            default => chr($major | 26).pack('N', $length),
        };
    }
}
