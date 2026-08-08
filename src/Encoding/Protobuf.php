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

use GMP;
use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Encodes the deterministic protobuf wire primitives used by TRON transactions.
 *
 * The implementation is deliberately small and schema-independent. Contract
 * schemas remain in the transaction layer, while this class owns the only
 * varint, length-delimited field, and signed int64 encoding implementation.
 */
final class Protobuf
{
    private const int MAX_FIELD_NUMBER = 536_870_911;

    /**
     * Encodes a proto3 signed integer field and omits its zero default.
     */
    public static function integerField(int $fieldNumber, int|string $value): string
    {
        $integer = self::signedInt64($value);
        if (gmp_cmp($integer, 0) === 0) {
            return '';
        }

        if (gmp_cmp($integer, 0) < 0) {
            $integer = gmp_add($integer, gmp_pow(2, 64));
        }

        return self::tag($fieldNumber, 0) . self::unsignedVarint($integer);
    }

    /**
     * Encodes a proto3 boolean field and omits its false default.
     */
    public static function booleanField(int $fieldNumber, bool $value): string
    {
        return $value ? self::tag($fieldNumber, 0) . "\x01" : '';
    }

    /**
     * Encodes a length-delimited bytes or string field and omits empty data.
     */
    public static function bytesField(int $fieldNumber, string $bytes): string
    {
        return $bytes === ''
            ? ''
            : self::lengthDelimitedField($fieldNumber, $bytes);
    }

    /**
     * Encodes a nested message while preserving explicit empty-message presence.
     */
    public static function messageField(int $fieldNumber, string $message): string
    {
        return self::lengthDelimitedField($fieldNumber, $message);
    }

    /**
     * Encodes a field tag and a length-delimited payload.
     */
    private static function lengthDelimitedField(int $fieldNumber, string $bytes): string
    {
        return self::tag($fieldNumber, 2)
            . self::unsignedVarint(gmp_init((string) strlen($bytes), 10))
            . $bytes;
    }

    /**
     * Encodes a validated protobuf field tag as an unsigned varint.
     */
    private static function tag(int $fieldNumber, int $wireType): string
    {
        if ($fieldNumber < 1 || $fieldNumber > self::MAX_FIELD_NUMBER) {
            throw new ValidationException('A protobuf field number is outside the supported range.');
        }

        return self::unsignedVarint(gmp_init((string) (($fieldNumber << 3) | $wireType), 10));
    }

    /**
     * Parses an exact decimal value inside the protobuf signed int64 range.
     */
    private static function signedInt64(int|string $value): GMP
    {
        $decimal = (string) $value;
        if (preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $decimal) !== 1 || $decimal === '-0') {
            throw new ValidationException('A protobuf integer must use canonical decimal notation.');
        }

        $integer = gmp_init($decimal, 10);
        $minimum = gmp_neg(gmp_pow(2, 63));
        $maximum = gmp_sub(gmp_pow(2, 63), 1);
        if (gmp_cmp($integer, $minimum) < 0 || gmp_cmp($integer, $maximum) > 0) {
            throw new ValidationException('A protobuf integer must fit in the signed int64 range.');
        }

        return $integer;
    }

    /**
     * Encodes one non-negative arbitrary-precision integer as a canonical varint.
     */
    private static function unsignedVarint(GMP $value): string
    {
        if (gmp_cmp($value, 0) < 0) {
            throw new ValidationException('A protobuf varint cannot encode a negative unsigned value.');
        }

        $bytes = '';
        do {
            $quotient = gmp_div_q($value, 128);
            $remainder = gmp_mod($value, 128);
            $byte = gmp_intval($remainder);
            $value = $quotient;
            $bytes .= chr($byte | (gmp_cmp($value, 0) > 0 ? 0x80 : 0));
        } while (gmp_cmp($value, 0) > 0);

        return $bytes;
    }
}
