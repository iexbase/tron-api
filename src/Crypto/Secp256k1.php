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

use BN\BN;
use Elliptic\Curve\ShortCurve\Point;
use Elliptic\EC;
use Elliptic\EC\KeyPair;
use Elliptic\EC\Signature as EllipticSignature;
use IEXBase\TronAPI\Exception\CryptoException;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Signature;
use kornrunner\Keccak;
use Throwable;

/**
 * Provides the package's single secp256k1 implementation and key derivation.
 */
final class Secp256k1
{
    private const CURVE_ORDER = 'fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141';

    /**
     * Generates a uniformly random private key in the valid secp256k1 range.
     */
    public static function generatePrivateKey(): string
    {
        do {
            $privateKey = bin2hex(random_bytes(32));
        } while (!self::isValidCurveScalar($privateKey));

        return $privateKey;
    }

    /**
     * Validates and canonicalizes a 32-byte private key.
     */
    public static function canonicalPrivateKey(#[\SensitiveParameter] string $privateKey): string
    {
        $canonical = Hex::canonicalize($privateKey, 32);
        if (!self::isValidCurveScalar($canonical)) {
            throw new CryptoException('The private key must be between 1 and the secp256k1 curve order minus one.');
        }

        return $canonical;
    }

    /**
     * Derives an uncompressed 65-byte public key from a private key.
     */
    public static function publicKeyFromPrivateKey(#[\SensitiveParameter] string $privateKey): string
    {
        return self::encodePublicKey(self::keyPairFromPrivateKey($privateKey), false);
    }

    /**
     * Derives a compressed 33-byte public key from a private key.
     */
    public static function compressedPublicKeyFromPrivateKey(#[\SensitiveParameter] string $privateKey): string
    {
        return self::encodePublicKey(self::keyPairFromPrivateKey($privateKey), true);
    }

    /**
     * Converts a compressed or uncompressed public key to 65-byte form.
     */
    public static function expandPublicKey(string $publicKey): string
    {
        return self::encodePublicKey(self::keyPairFromPublicKey($publicKey), false);
    }

    /**
     * Converts a compressed or uncompressed public key to 33-byte form.
     */
    public static function compressPublicKey(string $publicKey): string
    {
        return self::encodePublicKey(self::keyPairFromPublicKey($publicKey), true);
    }

    /**
     * Converts a compressed or uncompressed public key into a TRON address.
     */
    public static function addressFromPublicKey(string $publicKey): Address
    {
        $canonical = self::expandPublicKey($publicKey);

        $hash = Keccak::hash(Hex::toBytes(substr($canonical, 2)), 256);

        return Address::fromEvmHex(substr($hash, -40));
    }

    /**
     * Adds two valid private scalars modulo the secp256k1 curve order.
     */
    public static function addPrivateScalars(
        #[\SensitiveParameter] string $privateKey,
        #[\SensitiveParameter] string $tweak,
    ): string {
        $sum = gmp_mod(
            gmp_add(
                gmp_init(self::canonicalPrivateKey($privateKey), 16),
                gmp_init(self::canonicalPrivateKey($tweak), 16),
            ),
            gmp_init(self::CURVE_ORDER, 16),
        );

        if (gmp_cmp($sum, 0) === 0) {
            throw new CryptoException('The derived private key is zero and cannot be used.');
        }

        return str_pad(gmp_strval($sum, 16), 64, '0', STR_PAD_LEFT);
    }

    /**
     * Adds a private scalar's point to a compressed public key.
     */
    public static function addPublicKeyScalar(string $publicKey, #[\SensitiveParameter] string $tweak): string
    {
        try {
            $parentPoint = self::keyPairFromPublicKey($publicKey)->getPublic();
            $tweakPoint = self::keyPairFromPrivateKey($tweak)->getPublic();
        } catch (Throwable $exception) {
            throw new CryptoException('The public child key could not be derived.', 0, $exception);
        }

        if (!$parentPoint instanceof Point || !$tweakPoint instanceof Point) {
            throw new CryptoException('The secp256k1 library returned an invalid derivation point.');
        }

        $childPoint = $parentPoint->add($tweakPoint);
        if (!$childPoint instanceof Point || $childPoint->isInfinity()) {
            throw new CryptoException('The derived public key is the point at infinity.');
        }

        $encoded = $childPoint->encode('hex', true);
        if (!is_string($encoded)) {
            throw new CryptoException('The derived public key could not be encoded.');
        }

        return Hex::canonicalize($encoded, 33);
    }

    /**
     * Signs a 32-byte digest using deterministic canonical ECDSA.
     */
    public static function signDigest(string $digestHex, #[\SensitiveParameter] string $privateKey): Signature
    {
        $digest = Hex::canonicalize($digestHex, 32);

        try {
            $result = self::curve()->sign(
                $digest,
                self::canonicalPrivateKey($privateKey),
                'hex',
                ['canonical' => true],
            );
        } catch (Throwable $exception) {
            throw new CryptoException('The digest could not be signed.', 0, $exception);
        }

        if (!$result instanceof EllipticSignature
            || !$result->r instanceof BN
            || !$result->s instanceof BN
            || !is_int($result->recoveryParam)
        ) {
            throw new CryptoException('The secp256k1 library returned an invalid signature.');
        }

        if (!in_array($result->recoveryParam, [0, 1], true)) {
            throw new CryptoException('TRON signatures require a recovery byte of 0 or 1.');
        }

        $r = $result->r->toString(16);
        $s = $result->s->toString(16);
        if (!is_string($r) || !is_string($s)) {
            throw new CryptoException('The secp256k1 signature scalars could not be encoded.');
        }

        return Signature::fromComponents(
            str_pad($r, 64, '0', STR_PAD_LEFT),
            str_pad($s, 64, '0', STR_PAD_LEFT),
            $result->recoveryParam,
        );
    }

    /**
     * Recovers the signing TRON address from a digest and wire signature.
     */
    public static function recoverAddress(string $digestHex, Signature $signature): Address
    {
        $digest = Hex::canonicalize($digestHex, 32);

        try {
            $publicPoint = self::curve()->recoverPubKey(
                $digest,
                ['r' => $signature->rHex(), 's' => $signature->sHex()],
                $signature->recoveryId(),
                'hex',
            );
        } catch (Throwable $exception) {
            throw new CryptoException('The public key could not be recovered from the signature.', 0, $exception);
        }

        if (!$publicPoint instanceof Point) {
            throw new CryptoException('The secp256k1 library returned an invalid recovered public key.');
        }

        $publicKey = $publicPoint->encode('hex', false);
        if (!is_string($publicKey)) {
            throw new CryptoException('The recovered public key could not be encoded.');
        }

        return self::addressFromPublicKey($publicKey);
    }

    /**
     * Returns whether a 32-byte hexadecimal scalar is in the curve's range.
     */
    public static function isValidCurveScalar(string $hex): bool
    {
        if (preg_match('/^[0-9a-f]{64}$/D', $hex) !== 1) {
            return false;
        }

        $number = gmp_init($hex, 16);

        return gmp_cmp($number, 0) > 0 && gmp_cmp($number, gmp_init(self::CURVE_ORDER, 16)) < 0;
    }

    /**
     * Creates a fresh curve object to avoid shared mutable cryptographic state.
     */
    private static function curve(): EC
    {
        return new EC('secp256k1');
    }

    /**
     * Creates a validated private key pair without exposing it outside this class.
     */
    private static function keyPairFromPrivateKey(#[\SensitiveParameter] string $privateKey): KeyPair
    {
        try {
            $keyPair = self::curve()->keyFromPrivate(self::canonicalPrivateKey($privateKey), 'hex');
        } catch (Throwable $exception) {
            throw new CryptoException('The secp256k1 private key could not be loaded.', 0, $exception);
        }

        if (!$keyPair instanceof KeyPair) {
            throw new CryptoException('The secp256k1 library returned an invalid key pair.');
        }

        return $keyPair;
    }

    /**
     * Creates a validated public key pair from compressed or uncompressed input.
     */
    private static function keyPairFromPublicKey(string $publicKey): KeyPair
    {
        $canonical = Hex::canonicalize($publicKey);
        $expectedBytes = match (strlen($canonical)) {
            66 => 33,
            130 => 65,
            default => throw new CryptoException('A secp256k1 public key must contain 33 or 65 bytes.'),
        };
        Hex::canonicalize($canonical, $expectedBytes);

        if (($expectedBytes === 33 && !str_starts_with($canonical, '02') && !str_starts_with($canonical, '03'))
            || ($expectedBytes === 65 && !str_starts_with($canonical, '04'))
        ) {
            throw new CryptoException('The secp256k1 public key has an invalid encoding prefix.');
        }

        try {
            $keyPair = self::curve()->keyFromPublic($canonical, 'hex');
            $publicPoint = $keyPair instanceof KeyPair ? $keyPair->getPublic() : null;
        } catch (Throwable $exception) {
            throw new CryptoException('The secp256k1 public key could not be loaded.', 0, $exception);
        }

        if (!$keyPair instanceof KeyPair || !$publicPoint instanceof Point || !$publicPoint->validate()) {
            throw new CryptoException('The secp256k1 public key is not a valid curve point.');
        }

        return $keyPair;
    }

    /**
     * Encodes a key pair's public point in compressed or uncompressed form.
     */
    private static function encodePublicKey(KeyPair $keyPair, bool $compressed): string
    {
        $publicKey = $keyPair->getPublic($compressed, 'hex');
        if (!is_string($publicKey)) {
            throw new CryptoException('The secp256k1 public key could not be encoded.');
        }

        return Hex::canonicalize($publicKey, $compressed ? 33 : 65);
    }
}
