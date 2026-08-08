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

namespace IEXBase\TronAPI\Api;

use IEXBase\TronAPI\Exception\ResponseDecodingException;

/**
 * Converts untrusted JSON values into explicit PHP shapes without silent casts.
 */
final class DataDecoder
{
    /**
     * Returns a JSON object as a string-keyed array.
     *
     * @return array<string, mixed>
     */
    public static function object(mixed $value, string $field): array
    {
        if (!is_array($value) || array_is_list($value) && $value !== []) {
            throw new ResponseDecodingException(sprintf('The `%s` response field must be an object.', $field));
        }

        $object = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new ResponseDecodingException(sprintf('The `%s` response object contains a non-string key.', $field));
            }
            $object[$key] = $item;
        }

        return $object;
    }

    /**
     * Returns a JSON list containing only string-keyed objects.
     *
     * @return list<array<string, mixed>>
     */
    public static function objectList(mixed $value, string $field): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new ResponseDecodingException(sprintf('The `%s` response field must be a list.', $field));
        }

        return array_map(
            static fn (mixed $item): array => self::object($item, $field . '[]'),
            $value,
        );
    }

    /**
     * Returns a required string value without accepting numeric coercion.
     */
    public static function string(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new ResponseDecodingException(sprintf('The `%s` response field must be a string.', $field));
        }

        return $value;
    }

    /**
     * Returns a nullable string value while rejecting every other JSON type.
     */
    public static function optionalString(mixed $value, string $field): ?string
    {
        return $value === null ? null : self::string($value, $field);
    }

    /**
     * Returns an exact canonical non-negative integer as decimal text.
     */
    public static function unsignedDecimal(mixed $value, string $field): string
    {
        if (is_int($value) && $value >= 0) {
            return (string) $value;
        }

        if (is_string($value) && preg_match('/^(0|[1-9][0-9]*)$/D', $value) === 1) {
            return $value;
        }

        throw new ResponseDecodingException(sprintf(
            'The `%s` response field must be a non-negative integer without exponent notation.',
            $field,
        ));
    }

    /**
     * Returns an exact canonical signed integer as decimal text.
     */
    public static function signedDecimal(mixed $value, string $field): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value) && preg_match('/^(?:0|-?[1-9][0-9]*)$/D', $value) === 1) {
            return $value;
        }

        throw new ResponseDecodingException(sprintf(
            'The `%s` response field must be an integer without exponent notation.',
            $field,
        ));
    }

    /**
     * Returns a signed integer that must fit the current PHP platform exactly.
     */
    public static function signedInteger(mixed $value, string $field): int
    {
        $decimal = self::signedDecimal($value, $field);
        if (filter_var($decimal, FILTER_VALIDATE_INT) === false) {
            throw new ResponseDecodingException(sprintf('The `%s` response field exceeds the PHP integer range.', $field));
        }

        return (int) $decimal;
    }

    /**
     * Returns an integer that must fit the current PHP platform exactly.
     */
    public static function integer(mixed $value, string $field): int
    {
        $decimal = self::unsignedDecimal($value, $field);
        if (strlen($decimal) > strlen((string) PHP_INT_MAX)
            || (strlen($decimal) === strlen((string) PHP_INT_MAX) && strcmp($decimal, (string) PHP_INT_MAX) > 0)
        ) {
            throw new ResponseDecodingException(sprintf('The `%s` response field exceeds the PHP integer range.', $field));
        }

        return (int) $decimal;
    }

    /**
     * Returns a strict JSON boolean value.
     */
    public static function boolean(mixed $value, string $field): bool
    {
        if (!is_bool($value)) {
            throw new ResponseDecodingException(sprintf('The `%s` response field must be boolean.', $field));
        }

        return $value;
    }
}
