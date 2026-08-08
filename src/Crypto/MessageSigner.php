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

namespace IEXBase\TronAPI\Crypto;

use IEXBase\TronAPI\Exception\CryptoException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Signature;
use kornrunner\Keccak;

/**
 * Implements the current TronWeb-compatible Message Signature V2 protocol.
 *
 * The exact message bytes are prefixed with the TRON domain string and their
 * decimal byte length before Keccak-256 hashing. Text callers must therefore
 * pass UTF-8 bytes to match TronWeb's JavaScript string conversion.
 */
final class MessageSigner
{
    private const PREFIX = "\x19TRON Signed Message:\n";

    /**
     * Computes the 32-byte Message Signature V2 digest as hexadecimal text.
     */
    public static function digest(string $message): string
    {
        return Keccak::hash(
            self::PREFIX . (string) strlen($message) . $message,
            256,
        );
    }

    /**
     * Signs a message locally and verifies the recovered signer identity.
     */
    public static function sign(string $message, SignerInterface $signer): Signature
    {
        $signature = $signer->signDigest(self::digest($message));
        if (!self::recover($message, $signature)->equals($signer->address())) {
            throw new CryptoException('The message signature does not recover the expected signer address.');
        }

        return $signature;
    }

    /**
     * Recovers the signing TRON address from a signature object or V2 hex text.
     */
    public static function recover(string $message, Signature|string $signature): Address
    {
        $value = is_string($signature) ? Signature::fromMessageHex($signature) : $signature;

        return Secp256k1::recoverAddress(self::digest($message), $value);
    }

    /**
     * Verifies that a message signature recovers the expected TRON address.
     */
    public static function verify(
        string $message,
        Signature|string $signature,
        Address|string $expectedAddress,
    ): bool {
        $address = is_string($expectedAddress) ? Address::fromString($expectedAddress) : $expectedAddress;

        return self::recover($message, $signature)->equals($address);
    }
}
