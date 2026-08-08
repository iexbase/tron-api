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
use IEXBase\TronAPI\Exception\ValidationException;
use JsonSerializable;

/**
 * Represents an optional UTF-8 transaction memo before transaction construction.
 */
final readonly class Memo implements JsonSerializable
{
    private const MAX_BYTES = 500_000;

    /**
     * Stores valid UTF-8 text below the current transaction-size safety ceiling.
     */
    private function __construct(private string $text)
    {
        if (preg_match('//u', $text) !== 1) {
            throw new ValidationException('A transaction memo must contain valid UTF-8 text.');
        }

        if (strlen($text) > self::MAX_BYTES) {
            throw new ValidationException('A transaction memo is too large for a valid TRON transaction.');
        }
    }

    /**
     * Creates a memo from user-visible UTF-8 text.
     */
    public static function fromText(string $text): self
    {
        return new self($text);
    }

    /**
     * Returns the original UTF-8 text.
     */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * Returns the hexadecimal representation stored in transaction raw_data.
     */
    public function toHex(): string
    {
        return Hex::fromBytes($this->text, false);
    }

    /**
     * Serializes the user-visible memo text.
     */
    public function jsonSerialize(): string
    {
        return $this->text;
    }
}
