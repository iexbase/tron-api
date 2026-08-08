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

namespace IEXBase\TronAPI\Value;

use IEXBase\TronAPI\Encoding\Hex;
use JsonSerializable;

/**
 * Represents arbitrary binary ABI data without confusing it with UTF-8 text.
 */
final readonly class ByteString implements JsonSerializable
{
    /**
     * Stores arbitrary bytes exactly as supplied.
     */
    private function __construct(private string $bytes)
    {
    }

    /**
     * Creates a binary value from raw bytes.
     */
    public static function fromBytes(string $bytes): self
    {
        return new self($bytes);
    }

    /**
     * Creates a binary value from even hexadecimal text.
     */
    public static function fromHex(string $hex): self
    {
        if ($hex === '' || $hex === '0x' || $hex === '0X') {
            return new self('');
        }

        return new self(Hex::toBytes($hex));
    }

    /**
     * Returns the unmodified binary data.
     */
    public function bytes(): string
    {
        return $this->bytes;
    }

    /**
     * Returns lowercase hexadecimal text with an optional 0x prefix.
     */
    public function toHex(bool $withPrefix = true): string
    {
        return Hex::fromBytes($this->bytes, $withPrefix);
    }

    /**
     * Returns the exact number of bytes.
     */
    public function length(): int
    {
        return strlen($this->bytes);
    }

    /**
     * Serializes binary ABI data as unambiguous 0x-prefixed hexadecimal text.
     */
    public function jsonSerialize(): string
    {
        return $this->toHex();
    }
}
