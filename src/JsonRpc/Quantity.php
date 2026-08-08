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

namespace IEXBase\TronAPI\JsonRpc;

use GMP;
use IEXBase\TronAPI\Exception\ValidationException;
use JsonSerializable;

/**
 * Represents an arbitrary-precision JSON-RPC quantity with canonical 0x encoding.
 */
final readonly class Quantity implements JsonSerializable
{
    /**
     * Stores a non-negative integer after converting it to canonical hexadecimal.
     */
    private function __construct(private string $hex)
    {
    }

    /**
     * Creates a quantity from exact non-negative decimal notation.
     */
    public static function fromDecimal(int|string $value): self
    {
        $decimal = (string) $value;
        if (preg_match('/^(0|[1-9][0-9]*)$/D', $decimal) !== 1) {
            throw new ValidationException('A JSON-RPC quantity requires a non-negative decimal integer.');
        }

        return self::fromNumber(gmp_init($decimal, 10));
    }

    /**
     * Parses a canonical 0x-prefixed JSON-RPC quantity without leading zeroes.
     */
    public static function fromHex(string $value): self
    {
        if (preg_match('/^0x(?:0|[1-9a-fA-F][0-9a-fA-F]*)$/D', $value) !== 1) {
            throw new ValidationException('A JSON-RPC quantity must use canonical 0x-prefixed hexadecimal notation.');
        }

        return self::fromNumber(gmp_init(substr($value, 2), 16));
    }

    /**
     * Returns the exact decimal integer text.
     */
    public function decimal(): string
    {
        return gmp_strval(gmp_init(substr($this->hex, 2), 16), 10);
    }

    /**
     * Returns canonical lowercase 0x-prefixed hexadecimal notation.
     */
    public function hex(): string
    {
        return $this->hex;
    }

    /**
     * Serializes the canonical JSON-RPC quantity string.
     */
    public function jsonSerialize(): string
    {
        return $this->hex;
    }

    /**
     * Builds canonical quantity text from a non-negative GMP integer.
     */
    private static function fromNumber(GMP $number): self
    {
        return new self('0x' . gmp_strval($number, 16));
    }
}
