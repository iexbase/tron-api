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

namespace IEXBase\TronAPI\Tests\Asset;

use DateTimeImmutable;
use IEXBase\TronAPI\Asset\AssetIssuanceRequest;
use IEXBase\TronAPI\Asset\AssetUpdateRequest;
use IEXBase\TronAPI\Asset\FrozenSupply;
use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Model\Asset;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\NativeAssetAmount;
use IEXBase\TronAPI\Value\NativeAssetId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies complete TRC-10 issuance, update, identifier, and amount values.
 */
#[CoversClass(AssetIssuanceRequest::class)]
#[CoversClass(AssetUpdateRequest::class)]
#[CoversClass(FrozenSupply::class)]
#[CoversClass(NativeAssetId::class)]
#[CoversClass(NativeAssetAmount::class)]
final class AssetRequestTest extends TestCase
{
    /**
     * Produces every native issuance field without losing precision or timestamps.
     */
    public function testIssuanceProducesCompleteNativeFields(): void
    {
        $frozen = new FrozenSupply(Amount::fromDecimal('100.000', 3), 30);
        $request = new AssetIssuanceRequest(
            'Example Token',
            'EXT',
            Amount::fromDecimal('1000.000', 3),
            1,
            10,
            new DateTimeImmutable('2026-01-01T00:00:00.000Z'),
            new DateTimeImmutable('2027-01-01T00:00:00.000Z'),
            'A native token',
            'https://example.com/token',
            100,
            200,
            [$frozen],
        );
        $fields = $request->fields();

        self::assertSame(1_000_000, $fields['total_supply']);
        self::assertSame(3, $fields['precision']);
        self::assertSame(1_767_225_600_000, $fields['start_time']);
        self::assertSame([['frozen_amount' => 100_000, 'frozen_days' => 30]], $fields['frozen_supply']);
        self::assertSame([$frozen], $request->frozenSupplies());
    }

    /**
     * Rejects frozen portions whose combined amount exceeds the issued supply.
     */
    public function testIssuanceRejectsExcessFrozenSupply(): void
    {
        $this->expectException(ValidationException::class);

        new AssetIssuanceRequest(
            'Example',
            'EXT',
            Amount::fromDecimal('10'),
            1,
            1,
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2027-01-01T00:00:00Z'),
            '',
            'https://example.com',
            frozenSupplies: [new FrozenSupply(Amount::fromDecimal('11'), 1)],
        );
    }

    /**
     * Maps mutable metadata and quotas to UpdateAssetContract names.
     */
    public function testUpdateRequestUsesNativeQuotaNames(): void
    {
        $request = new AssetUpdateRequest('', 'https://example.com/new', 10, 20);

        self::assertSame([
            'description' => '',
            'url' => 'https://example.com/new',
            'new_limit' => 10,
            'new_public_limit' => 20,
        ], $request->fields());
    }

    /**
     * Keeps TRX and TRC-10 identifiers distinct and enforces their decimal scales.
     */
    public function testNativeAssetValuesRetainProtocolIdentity(): void
    {
        $asset = $this->asset();
        $trx = NativeAssetAmount::trx(Amount::fromDecimal('1'));
        $token = NativeAssetAmount::trc10($asset, Amount::fromDecimal('1.250', 3));

        self::assertTrue($trx->assetId->isTrx());
        self::assertSame('_', $trx->assetId->value());
        self::assertSame('1002000', $token->assetId->value());
        self::assertTrue($token->assetId->equals(NativeAssetId::fromNodeHex(bin2hex('1002000'))));
        self::assertSame('1.25', $token->amount->decimalValue());
    }

    /**
     * Creates deterministic TRC-10 metadata for value-object tests.
     */
    private function asset(): Asset
    {
        return Asset::fromNodeData([
            'id' => '1002000',
            'name' => 'Example Token',
            'abbr' => 'EXT',
            'owner_address' => (new LocalPrivateKeySigner(str_pad('1', 64, '0', STR_PAD_LEFT)))->address()->toBase58(),
            'precision' => 3,
            'total_supply' => '1000000',
            'start_time' => 1,
            'end_time' => 2,
        ]);
    }
}
