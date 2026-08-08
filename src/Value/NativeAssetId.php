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
use IEXBase\TronAPI\Model\Asset;
use JsonSerializable;

/**
 * Represents the native `_` TRX identifier or one numeric TRC-10 token ID.
 */
final readonly class NativeAssetId implements JsonSerializable
{
    public const TRX = '_';

    /**
     * Stores one already validated protocol-level asset identifier.
     */
    private function __construct(private string $value)
    {
    }

    /**
     * Creates the reserved native TRX identifier.
     */
    public static function trx(): self
    {
        return new self(self::TRX);
    }

    /**
     * Creates a native identifier from typed TRC-10 metadata.
     */
    public static function trc10(Asset $asset): self
    {
        return new self($asset->id);
    }

    /**
     * Parses `_` or a canonical positive TRC-10 decimal identifier.
     */
    public static function fromString(string $value): self
    {
        if ($value !== self::TRX && preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new ValidationException('A native asset ID must be `_` for TRX or a positive TRC-10 decimal ID.');
        }

        return new self($value);
    }

    /**
     * Decodes a protobuf bytes field returned as hexadecimal JSON text.
     */
    public static function fromNodeHex(string $hex): self
    {
        $value = Hex::toBytes($hex);

        return self::fromString($value);
    }

    /**
     * Returns `_` or the exact numeric TRC-10 identifier.
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Returns whether this identifier represents native TRX.
     */
    public function isTrx(): bool
    {
        return $this->value === self::TRX;
    }

    /**
     * Compares exact protocol asset identifiers.
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    /**
     * Serializes the plain protocol identifier used by visible=true requests.
     */
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
