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

namespace IEXBase\TronAPI\Tests\Support;

use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Enum\ResourceType;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Support\IntegerHelper;
use IEXBase\TronAPI\Support\StakeHelper;
use IEXBase\TronAPI\Support\TextHelper;
use IEXBase\TronAPI\Value\Amount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies shared validation rules used by protocol services and value objects.
 */
#[CoversClass(IntegerHelper::class)]
#[CoversClass(StakeHelper::class)]
#[CoversClass(TextHelper::class)]
#[CoversClass(ConfirmationLevel::class)]
#[CoversClass(ResourceType::class)]
final class HelperTest extends TestCase
{
    /**
     * Returns values that satisfy inclusive, non-negative, and positive bounds.
     */
    public function testIntegerRulesReturnAcceptedValues(): void
    {
        self::assertSame(5, IntegerHelper::between(5, 1, 10, 'Value'));
        self::assertSame(0, IntegerHelper::nonNegative(0, 'Value'));
        self::assertSame(1, IntegerHelper::positive(1, 'Value'));
    }

    /**
     * Rejects an invalid inclusive range configuration or out-of-range value.
     */
    public function testIntegerRangeRejectsInvalidValue(): void
    {
        $this->expectException(ValidationException::class);
        IntegerHelper::between(11, 1, 10, 'Value');
    }

    /**
     * Applies exact staking amount, resource, and protobuf-default behavior.
     */
    public function testStakeRulesAreSharedAcrossVersions(): void
    {
        self::assertSame(1_500_000, StakeHelper::positiveSun(Amount::fromDecimal('1.5')));
        self::assertSame(['resource'], StakeHelper::omittableResourceField(ResourceType::Bandwidth));
        self::assertSame([], StakeHelper::omittableResourceField(ResourceType::Energy));
        self::assertSame(2, ResourceType::TronPower->code());
        self::assertTrue(ConfirmationLevel::Confirmed->isConfirmed());
        self::assertFalse(ConfirmationLevel::Latest->isConfirmed());

        $this->expectException(ValidationException::class);
        StakeHelper::requireBandwidthOrEnergy(ResourceType::TronPower, 'Delegation');
    }

    /**
     * Validates UTF-8, printable ASCII, and credential-free web URLs.
     */
    public function testTextRulesRetainAcceptedInput(): void
    {
        self::assertSame('TRON', TextHelper::utf8('TRON', 'Name', 10));
        self::assertSame('account-1', TextHelper::printableAscii('account-1', 'ID', 1, 20));
        self::assertSame('https://example.com/path', TextHelper::webUrl('https://example.com/path', 'URL'));
    }

    /**
     * Rejects URL credentials that could leak through metadata or logs.
     */
    public function testTextRulesRejectCredentialBearingUrl(): void
    {
        foreach ([
            'https://user@example.com',
            'https://user:secret@example.com',
        ] as $url) {
            try {
                TextHelper::webUrl($url, 'URL');
                self::fail(sprintf('The credential-bearing URL `%s` was accepted.', $url));
            } catch (ValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
