<?php

declare(strict_types=1);

/**
 * TronAPI 6.0
 *
 * Copyright (c) 2018-2026 iEXBase.
 *
 * @author  Shamsudin Serderov <steein.shamsudin@gmail.com>
 * @license https://github.com/iexbase/tron-api/blob/master/LICENSE MIT License
 * @link    https://github.com/iexbase/tron-api
 */

namespace IEXBase\TronAPI\Encoding;

use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Provides the package's single strict implementation of hexadecimal encoding.
 */
final class Hex
{
    /**
     * Validates and returns lowercase hexadecimal data without a 0x prefix.
     *
     * @param string   $value Hexadecimal text with an optional 0x prefix.
     * @param int|null $expectedBytes Exact byte length when one is required.
     */
    public static function canonicalize(string $value, ?int $expectedBytes = null): string
    {
        $hex = str_starts_with($value, '0x') || str_starts_with($value, '0X')
            ? substr($value, 2)
            : $value;

        if ($hex === '' || strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
            throw new ValidationException('The value must contain an even number of hexadecimal characters.');
        }

        if ($expectedBytes !== null && strlen($hex) !== $expectedBytes * 2) {
            throw new ValidationException(sprintf('The hexadecimal value must contain exactly %d bytes.', $expectedBytes));
        }

        return strtolower($hex);
    }

    /**
     * Converts validated hexadecimal text into its binary representation.
     *
     * @param string   $value Hexadecimal text with an optional 0x prefix.
     * @param int|null $expectedBytes Exact byte length when one is required.
     */
    public static function toBytes(string $value, ?int $expectedBytes = null): string
    {
        $bytes = hex2bin(self::canonicalize($value, $expectedBytes));

        if ($bytes === false) {
            throw new ValidationException('The hexadecimal value could not be decoded.');
        }

        return $bytes;
    }

    /**
     * Encodes arbitrary binary data as lowercase hexadecimal text.
     *
     * @param string $bytes Binary data to encode.
     * @param bool   $withPrefix Whether to include the conventional 0x prefix.
     */
    public static function fromBytes(string $bytes, bool $withPrefix = false): string
    {
        $hex = bin2hex($bytes);

        return $withPrefix ? '0x' . $hex : $hex;
    }

    /**
     * Encodes a valid UTF-8 string for TRON memo and metadata fields.
     */
    public static function encodeUtf8(string $value): string
    {
        if (preg_match('//u', $value) !== 1) {
            throw new ValidationException('The value must be valid UTF-8 text.');
        }

        return bin2hex($value);
    }

    /**
     * Decodes hexadecimal bytes into a UTF-8 string and validates the result.
     */
    public static function decodeUtf8(string $value): string
    {
        $decoded = self::toBytes($value);

        if (preg_match('//u', $decoded) !== 1) {
            throw new ValidationException('The hexadecimal value does not contain valid UTF-8 text.');
        }

        return $decoded;
    }
}
