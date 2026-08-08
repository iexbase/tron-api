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

use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Encoding\Base58Check;
use IEXBase\TronAPI\Encoding\Hex;
use JsonSerializable;

/**
 * Represents one immutable TRON address in all supported API encodings.
 *
 * The canonical payload is always 21 bytes: the current TRON `0x41` network
 * prefix followed by the 20-byte account identifier. Base58 input is accepted
 * only after its checksum is verified.
 */
final readonly class Address implements JsonSerializable
{
    public const PREFIX_BYTE = "\x41";
    public const PREFIX_HEX = '41';
    public const PAYLOAD_BYTES = 21;
    public const EVM_BYTES = 20;

    /**
     * Stores an already validated 21-byte TRON address payload.
     */
    private function __construct(private string $payload)
    {
    }

    /**
     * Parses either a Base58Check address or a 21-byte hexadecimal address.
     */
    public static function fromString(string $address): self
    {
        return str_starts_with($address, 'T')
            ? self::fromBase58($address)
            : self::fromHex($address);
    }

    /**
     * Parses and validates a user-facing Base58Check TRON address.
     */
    public static function fromBase58(string $address): self
    {
        if (strlen($address) !== 34) {
            throw new ValidationException('A Base58Check TRON address must contain exactly 34 characters.');
        }

        return self::fromPayload(Base58Check::decode($address));
    }

    /**
     * Parses a 21-byte hexadecimal TRON address with the `41` prefix.
     */
    public static function fromHex(string $address): self
    {
        return self::fromPayload(Hex::toBytes($address, self::PAYLOAD_BYTES));
    }

    /**
     * Parses a 20-byte ABI/EVM address and restores the TRON network prefix.
     */
    public static function fromEvmHex(string $address): self
    {
        return self::fromPayload(self::PREFIX_BYTE . Hex::toBytes($address, self::EVM_BYTES));
    }

    /**
     * Returns whether a string is a valid Base58Check or hexadecimal address.
     */
    public static function isValid(string $address): bool
    {
        try {
            self::fromString($address);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Creates an address from a validated 21-byte binary payload.
     */
    private static function fromPayload(string $payload): self
    {
        if (strlen($payload) !== self::PAYLOAD_BYTES || !str_starts_with($payload, self::PREFIX_BYTE)) {
            throw new ValidationException('A TRON address must contain 21 bytes and use the 0x41 prefix.');
        }

        return new self($payload);
    }

    /**
     * Returns the checksummed user-facing Base58 representation.
     */
    public function toBase58(): string
    {
        return Base58Check::encode($this->payload);
    }

    /**
     * Returns the 21-byte hexadecimal representation used by node APIs.
     */
    public function toHex(bool $withPrefix = false): string
    {
        return Hex::fromBytes($this->payload, $withPrefix);
    }

    /**
     * Returns the 20-byte hexadecimal representation used by Solidity ABI.
     */
    public function toEvmHex(bool $withPrefix = true): string
    {
        return Hex::fromBytes(substr($this->payload, 1), $withPrefix);
    }

    /**
     * Returns the canonical 21-byte binary payload for cryptographic protocols.
     */
    public function bytes(): string
    {
        return $this->payload;
    }

    /**
     * Compares the canonical binary payloads of two addresses.
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->payload, $other->payload);
    }

    /**
     * Returns Base58 text when the address is used as a string.
     */
    public function __toString(): string
    {
        return $this->toBase58();
    }

    /**
     * Serializes the address as its checksummed Base58 representation.
     */
    public function jsonSerialize(): string
    {
        return $this->toBase58();
    }
}
