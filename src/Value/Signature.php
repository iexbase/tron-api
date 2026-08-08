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

use IEXBase\TronAPI\Crypto\Secp256k1;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Encoding\Hex;
use JsonSerializable;

/**
 * Represents the 65-byte TRON ECDSA wire format `r || s || v`.
 */
final readonly class Signature implements JsonSerializable
{
    /**
     * Stores validated 32-byte r/s values and the one-byte recovery identifier.
     */
    private function __construct(
        private string $r,
        private string $s,
        private int $recoveryId,
    ) {
    }

    /**
     * Parses a 65-byte hexadecimal TRON signature.
     */
    public static function fromHex(string $signature): self
    {
        return self::fromWireHex($signature, 0);
    }

    /**
     * Parses a 65-byte TRON Message Signature V2 value with v equal to 27 or 28.
     */
    public static function fromMessageHex(string $signature): self
    {
        return self::fromWireHex($signature, 27);
    }

    /**
     * Parses a wire signature whose final byte uses the requested recovery offset.
     */
    private static function fromWireHex(string $signature, int $recoveryOffset): self
    {
        $canonical = Hex::canonicalize($signature, 65);
        $wireRecoveryId = ord(Hex::toBytes(substr($canonical, 128, 2), 1));
        $recoveryId = $wireRecoveryId - $recoveryOffset;

        return self::fromComponents(
            substr($canonical, 0, 64),
            substr($canonical, 64, 64),
            $recoveryId,
        );
    }

    /**
     * Creates a signature from exact r, s, and v components.
     */
    public static function fromComponents(string $r, string $s, int $recoveryId): self
    {
        $canonicalR = Hex::canonicalize($r, 32);
        $canonicalS = Hex::canonicalize($s, 32);

        if (!in_array($recoveryId, [0, 1], true)) {
            throw new ValidationException('A TRON signature recovery byte must be 0 or 1.');
        }

        if (!Secp256k1::isValidCurveScalar($canonicalR)
            || !Secp256k1::isValidCurveScalar($canonicalS)
        ) {
            throw new ValidationException('Signature r and s values must be valid non-zero secp256k1 scalars.');
        }

        return new self($canonicalR, $canonicalS, $recoveryId);
    }

    /**
     * Returns the 32-byte r component without a prefix.
     */
    public function rHex(): string
    {
        return $this->r;
    }

    /**
     * Returns the 32-byte s component without a prefix.
     */
    public function sHex(): string
    {
        return $this->s;
    }

    /**
     * Returns the TRON recovery identifier stored as the final wire byte.
     */
    public function recoveryId(): int
    {
        return $this->recoveryId;
    }

    /**
     * Returns the complete lowercase `r || s || v` hexadecimal representation.
     */
    public function toHex(): string
    {
        return $this->wireHex(0, false);
    }

    /**
     * Returns TRON Message Signature V2 hex with v encoded as 27 or 28.
     */
    public function toMessageHex(bool $withPrefix = true): string
    {
        return $this->wireHex(27, $withPrefix);
    }

    /**
     * Recovers the signing address for a 32-byte transaction digest.
     */
    public function recoverAddress(string $digestHex): Address
    {
        return Secp256k1::recoverAddress($digestHex, $this);
    }

    /**
     * Serializes the signature as its TRON hexadecimal wire representation.
     */
    public function jsonSerialize(): string
    {
        return $this->toHex();
    }

    /**
     * Encodes r, s, and the recovery byte using a protocol-specific offset.
     */
    private function wireHex(int $recoveryOffset, bool $withPrefix): string
    {
        $hex = $this->r
            . $this->s
            . str_pad(dechex($this->recoveryId + $recoveryOffset), 2, '0', STR_PAD_LEFT);

        return $withPrefix ? '0x' . $hex : $hex;
    }
}
