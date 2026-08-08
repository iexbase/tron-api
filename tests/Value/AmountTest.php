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

namespace IEXBase\TronAPI\Tests\Value;

use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;
use IEXBase\TronAPI\Value\Memo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies exact amount arithmetic and unambiguous byte/text values.
 */
#[CoversClass(Amount::class)]
#[CoversClass(ByteString::class)]
#[CoversClass(Memo::class)]
final class AmountTest extends TestCase
{
    /**
     * Converts exact TRX decimal text without binary floating-point arithmetic.
     */
    public function testExactDecimalConversion(): void
    {
        $amount = Amount::fromDecimal('1.230000');

        self::assertSame('1230000', $amount->atomicValue());
        self::assertSame('1.23', $amount->decimalValue());
        self::assertSame('1.230000', $amount->decimalValue(false));
    }

    /**
     * Performs arbitrary-precision same-scale arithmetic without global bcscale.
     */
    public function testArbitraryPrecisionArithmetic(): void
    {
        $left = Amount::fromAtomic('922337203685477580812345', 6);
        $right = Amount::fromAtomic('1000000', 6);

        self::assertSame('922337203685477581812345', $left->add($right)->atomicValue());
        self::assertSame($left->atomicValue(), $left->add($right)->subtract($right)->atomicValue());
        self::assertSame('2000000', $right->multiplyByInteger(2)->atomicValue());
    }

    /**
     * Rejects exponent notation and significant precision loss.
     */
    public function testAmountRejectsUnsafeNotation(): void
    {
        $this->expectException(ValidationException::class);

        Amount::fromDecimal('1e-6');
    }

    /**
     * Rejects arithmetic across different token decimal scales.
     */
    public function testAmountRejectsDifferentScales(): void
    {
        $this->expectException(ValidationException::class);

        Amount::fromAtomic(1, 6)->add(Amount::fromAtomic(1, 18));
    }

    /**
     * Keeps arbitrary bytes distinct from UTF-8 transaction memo text.
     */
    public function testBytesAndMemoHaveExplicitEncodings(): void
    {
        $bytes = ByteString::fromBytes("\x00\xff");
        $memo = Memo::fromText('TRON ✓');

        self::assertSame('0x00ff', $bytes->toHex());
        self::assertSame('54524f4e20e29c93', $memo->toHex());
        self::assertSame('TRON ✓', $memo->text());
    }
}
