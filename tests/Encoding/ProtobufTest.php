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

namespace IEXBase\TronAPI\Tests\Encoding;

use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Encoding\Protobuf;
use IEXBase\TronAPI\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the deterministic protobuf primitives used for transaction integrity.
 */
#[CoversClass(Protobuf::class)]
final class ProtobufTest extends TestCase
{
    /**
     * Encodes signed int64 values with the exact canonical protobuf varint form.
     */
    public function testSignedInt64UsesProtocolVarintEncoding(): void
    {
        self::assertSame('', Protobuf::integerField(1, 0));
        self::assertSame('089601', Hex::fromBytes(Protobuf::integerField(1, 150)));
        self::assertSame(
            '08ffffffffffffffffff01',
            Hex::fromBytes(Protobuf::integerField(1, -1)),
        );
    }

    /**
     * Distinguishes omitted default bytes from an explicitly present empty message.
     */
    public function testLengthDelimitedDefaultsPreserveMessagePresence(): void
    {
        self::assertSame('', Protobuf::bytesField(1, ''));
        self::assertSame('0a00', Hex::fromBytes(Protobuf::messageField(1, '')));
        self::assertSame('', Protobuf::booleanField(1, false));
        self::assertSame('0801', Hex::fromBytes(Protobuf::booleanField(1, true)));
    }

    /**
     * Rejects decimal values outside the official signed int64 range.
     */
    public function testIntegerOutsideSignedInt64RangeIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        Protobuf::integerField(1, '9223372036854775808');
    }
}
