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

use IEXBase\TronAPI\Encoding\Base58;
use IEXBase\TronAPI\Encoding\Base58Check;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies strict hexadecimal, Base58, Base58Check, and TRON address behavior.
 */
#[CoversClass(Address::class)]
#[CoversClass(Base58::class)]
#[CoversClass(Base58Check::class)]
#[CoversClass(Hex::class)]
final class AddressEncodingTest extends TestCase
{
    private const HEX_ADDRESS = '41928c9af0651632157ef27a2cf17ca72c575a4d21';
    private const BASE58_ADDRESS = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';

    /**
     * Confirms all supported address representations round-trip exactly.
     */
    public function testAddressRepresentationsRoundTrip(): void
    {
        $address = Address::fromHex(self::HEX_ADDRESS);

        self::assertSame(self::BASE58_ADDRESS, $address->toBase58());
        self::assertSame(self::HEX_ADDRESS, $address->toHex());
        self::assertSame('0x' . substr(self::HEX_ADDRESS, 2), $address->toEvmHex());
        self::assertTrue(Address::fromBase58(self::BASE58_ADDRESS)->equals($address));
        self::assertTrue(Address::fromEvmHex(substr(self::HEX_ADDRESS, 2))->equals($address));
    }

    /**
     * Confirms Base58 preserves binary leading zero bytes.
     */
    public function testBase58PreservesLeadingZeros(): void
    {
        $bytes = "\0\0Hello TRON";

        self::assertSame($bytes, Base58::decode(Base58::encode($bytes)));
    }

    /**
     * Rejects a user-facing address whose checksum was modified.
     */
    public function testAddressRejectsInvalidChecksum(): void
    {
        $this->expectException(ValidationException::class);

        Address::fromBase58(substr(self::BASE58_ADDRESS, 0, -1) . 'X');
    }

    /**
     * Rejects ambiguous hexadecimal input with an odd number of digits.
     */
    public function testHexRejectsOddLength(): void
    {
        $this->expectException(ValidationException::class);

        Hex::canonicalize('0xabc');
    }

    /**
     * Reports validity without leaking a parsing exception to callers.
     */
    public function testAddressValidityCheckIsStrict(): void
    {
        self::assertTrue(Address::isValid(self::BASE58_ADDRESS));
        self::assertFalse(Address::isValid('TInvalidAddress0000000000000000000'));
        self::assertFalse(Address::isValid('001122'));
    }
}
