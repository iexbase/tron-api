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
use IEXBase\TronAPI\Crypto\MessageSigner;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Signature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies byte-for-byte compatibility with official TronWeb V2 signatures.
 */
#[CoversClass(MessageSigner::class)]
#[CoversClass(Signature::class)]
final class MessageSignerTest extends TestCase
{
    private const PRIVATE_KEY = '0000000000000000000000000000000000000000000000000000000000000001';
    private const ADDRESS = 'TMVQGm1qAQYVdetCeGRRkTWYYrLXuHK2HC';

    /**
     * Matches the published TronWeb string digest and deterministic signature.
     */
    public function testSignsOfficialTronWebStringVector(): void
    {
        $signature = MessageSigner::sign('hello world', new LocalPrivateKeySigner(self::PRIVATE_KEY));

        self::assertSame(
            'cf02daeb2bea196ed5692322a66ed50080ce74ff8cb711199f1b04f3c13bc10d',
            MessageSigner::digest('hello world'),
        );
        self::assertSame(
            '0x0dc0b53d525e0103a6013061cf18e60cf158809149f2b8994a545af65a7004cb'
            . '1eeaff560e801ab51b28df5d42549aa024c2aa7e9d34de1e01294b9afb5e6c7e1c',
            $signature->toMessageHex(),
        );
        self::assertSame(self::ADDRESS, MessageSigner::recover('hello world', $signature)->toBase58());
    }

    /**
     * Counts UTF-8 bytes exactly like TronWeb's toUtf8Bytes conversion.
     */
    public function testSignsUtf8MessageUsingByteLength(): void
    {
        $signature = MessageSigner::sign('Привет, TRON', new LocalPrivateKeySigner(self::PRIVATE_KEY));

        self::assertSame(
            '0x894287d9e4df0307bcd64c6c5189299496e4656a468dd86e6fe7e051744d2805'
            . '60484a20425dd7cda7908e59c547805a842fb337ab62150f92bed5f73442965d1b',
            $signature->toMessageHex(),
        );
        self::assertTrue(MessageSigner::verify('Привет, TRON', $signature, self::ADDRESS));
        self::assertFalse(MessageSigner::verify('Привет, tron', $signature, self::ADDRESS));
    }

    /**
     * Parses the V2 27/28 recovery byte and preserves its wire representation.
     */
    public function testParsesMessageSignatureHex(): void
    {
        $wire = '0x0dc0b53d525e0103a6013061cf18e60cf158809149f2b8994a545af65a7004cb'
            . '1eeaff560e801ab51b28df5d42549aa024c2aa7e9d34de1e01294b9afb5e6c7e1c';
        $signature = Signature::fromMessageHex($wire);

        self::assertSame(1, $signature->recoveryId());
        self::assertSame($wire, $signature->toMessageHex());
        self::assertTrue(MessageSigner::verify('hello world', $wire, self::ADDRESS));
    }

    /**
     * Rejects a transaction-style recovery byte in a V2 message signature.
     */
    public function testRejectsWrongMessageRecoveryEncoding(): void
    {
        $this->expectException(ValidationException::class);

        Signature::fromMessageHex(str_repeat('01', 64) . '01');
    }
}
