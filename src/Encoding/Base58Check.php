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
 * Implements Base58Check with mandatory double-SHA-256 checksum verification.
 */
final class Base58Check
{
    private const CHECKSUM_BYTES = 4;

    /**
     * Appends the checksum to a payload and returns Base58 text.
     */
    public static function encode(string $payload): string
    {
        if ($payload === '') {
            throw new ValidationException('A Base58Check payload must not be empty.');
        }

        return Base58::encode($payload . self::checksum($payload));
    }

    /**
     * Returns the payload only after validating the encoded checksum.
     */
    public static function decode(string $encoded): string
    {
        $decoded = Base58::decode($encoded);
        if (strlen($decoded) <= self::CHECKSUM_BYTES) {
            throw new ValidationException('The Base58Check value is too short.');
        }

        $payload = substr($decoded, 0, -self::CHECKSUM_BYTES);
        $checksum = substr($decoded, -self::CHECKSUM_BYTES);

        if (!hash_equals(self::checksum($payload), $checksum)) {
            throw new ValidationException('The Base58Check checksum is invalid.');
        }

        return $payload;
    }

    /**
     * Computes the first four bytes of a payload's double-SHA-256 digest.
     */
    private static function checksum(string $payload): string
    {
        return substr(hash('sha256', hash('sha256', $payload, true), true), 0, self::CHECKSUM_BYTES);
    }
}
