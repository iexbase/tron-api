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
 * Encodes and decodes the Bitcoin Base58 alphabet without accepting bad input.
 */
final class Base58
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /**
     * Encodes binary data while preserving each leading zero byte as `1`.
     */
    public static function encode(string $bytes): string
    {
        if ($bytes === '') {
            throw new ValidationException('Base58 input must not be empty.');
        }

        $number = gmp_import($bytes, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);
        $encoded = '';
        while (gmp_cmp($number, 0) > 0) {
            $remainder = gmp_mod($number, 58);
            $number = gmp_div_q($number, 58);
            $encoded = self::ALPHABET[gmp_intval($remainder)] . $encoded;
        }

        $leadingZeroBytes = strspn($bytes, "\0");

        return str_repeat('1', $leadingZeroBytes) . $encoded;
    }

    /**
     * Decodes Base58 text and rejects whitespace or non-alphabet characters.
     */
    public static function decode(string $encoded): string
    {
        if ($encoded === '') {
            throw new ValidationException('Base58 text must not be empty.');
        }

        $number = gmp_init(0, 10);
        $length = strlen($encoded);

        for ($index = 0; $index < $length; ++$index) {
            $position = strpos(self::ALPHABET, $encoded[$index]);
            if ($position === false) {
                throw new ValidationException(sprintf('Invalid Base58 character at offset %d.', $index));
            }

            $number = gmp_add(gmp_mul($number, 58), $position);
        }

        $bytes = gmp_cmp($number, 0) === 0
            ? ''
            : gmp_export($number, 1, GMP_MSW_FIRST | GMP_BIG_ENDIAN);

        return str_repeat("\0", strspn($encoded, '1')) . $bytes;
    }
}
