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

namespace IEXBase\TronAPI\Support;

use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Centralizes byte-length and character-set rules for protocol text fields.
 */
final class TextHelper
{
    /**
     * Returns valid UTF-8 text whose encoded byte length is within the limit.
     */
    public static function utf8(
        string $value,
        string $field,
        int $maximumBytes,
        bool $allowEmpty = false,
    ): string {
        if (preg_match('//u', $value) !== 1) {
            throw new ValidationException(sprintf('%s must contain valid UTF-8 text.', $field));
        }

        $length = strlen($value);
        if ((!$allowEmpty && $length === 0) || $length > $maximumBytes) {
            $minimum = $allowEmpty ? 0 : 1;
            throw new ValidationException(sprintf(
                '%s must contain between %d and %d UTF-8 bytes.',
                $field,
                $minimum,
                $maximumBytes,
            ));
        }

        return $value;
    }

    /**
     * Returns printable ASCII text whose byte length is within the limit.
     */
    public static function printableAscii(
        string $value,
        string $field,
        int $minimumBytes,
        int $maximumBytes,
    ): string {
        $length = strlen($value);
        if ($length < $minimumBytes
            || $length > $maximumBytes
            || preg_match('/^[\x20-\x7e]+$/D', $value) !== 1
        ) {
            throw new ValidationException(sprintf(
                '%s must contain %d to %d printable ASCII bytes.',
                $field,
                $minimumBytes,
                $maximumBytes,
            ));
        }

        return $value;
    }

    /**
     * Returns a bounded absolute HTTP or HTTPS URL suitable for protocol metadata.
     */
    public static function webUrl(
        string $value,
        string $field,
        int $maximumBytes = 256,
        bool $allowEmpty = false,
    ): string {
        self::utf8($value, $field, $maximumBytes, $allowEmpty);
        if ($allowEmpty && $value === '') {
            return '';
        }

        $parts = parse_url($value);
        if ($parts === false
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new ValidationException(sprintf('%s must be an absolute HTTP(S) URL without credentials.', $field));
        }

        return $value;
    }
}
