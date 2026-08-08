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

namespace IEXBase\TronAPI\Tests\Crypto;

use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Crypto\Secp256k1;
use IEXBase\TronAPI\Exception\CryptoException;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Signature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies local key generation, derivation, signing, recovery, and redaction.
 */
#[CoversClass(LocalPrivateKeySigner::class)]
#[CoversClass(Secp256k1::class)]
#[CoversClass(Signature::class)]
final class CryptoTest extends TestCase
{
    private const PRIVATE_KEY = '0000000000000000000000000000000000000000000000000000000000000001';
    private const ADDRESS = 'TMVQGm1qAQYVdetCeGRRkTWYYrLXuHK2HC';

    /**
     * Derives the known secp256k1 scalar-one public identity.
     */
    public function testKnownPrivateKeyDerivesExpectedAddress(): void
    {
        $signer = new LocalPrivateKeySigner(self::PRIVATE_KEY);

        self::assertSame(self::ADDRESS, $signer->address()->toBase58());
        self::assertSame(130, strlen($signer->publicKeyHex()));
    }

    /**
     * Recovers the signing address from the canonical TRON wire signature.
     */
    public function testSignatureRecoversSigner(): void
    {
        $signer = new LocalPrivateKeySigner(self::PRIVATE_KEY);
        $digest = hash('sha256', 'TronAPI 6.0');
        $signature = $signer->signDigest($digest);

        self::assertTrue($signature->recoverAddress($digest)->equals($signer->address()));
        self::assertSame(130, strlen($signature->toHex()));
    }

    /**
     * Rejects external signature scalars at or beyond the secp256k1 curve order.
     */
    public function testSignatureRejectsOutOfRangeCurveScalar(): void
    {
        $this->expectException(ValidationException::class);

        Signature::fromComponents(
            'fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141',
            self::PRIVATE_KEY,
            0,
        );
    }

    /**
     * Ensures debugger output never exposes private key material.
     */
    public function testDebuggerOutputRedactsPrivateKey(): void
    {
        $output = print_r(new LocalPrivateKeySigner(self::PRIVATE_KEY), true);

        self::assertStringContainsString('[REDACTED]', $output);
        self::assertStringNotContainsString(self::PRIVATE_KEY, $output);
    }

    /**
     * Prevents accidental persistence of local signers.
     */
    public function testSignerCannotBeSerialized(): void
    {
        $this->expectException(CryptoException::class);

        serialize(new LocalPrivateKeySigner(self::PRIVATE_KEY));
    }

    /**
     * Produces random valid signers without a node-side key endpoint.
     */
    public function testGeneratedSignerHasValidIdentity(): void
    {
        $signer = LocalPrivateKeySigner::generate();

        self::assertSame(64, strlen($signer->exportPrivateKey()));
        self::assertTrue(Address::isValid($signer->address()->toBase58()));
    }
}
