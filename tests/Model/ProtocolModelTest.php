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

namespace IEXBase\TronAPI\Tests\Model;

use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Exception\TransactionException;
use IEXBase\TronAPI\Model\EnergyEstimate;
use IEXBase\TronAPI\Model\Exchange;
use IEXBase\TronAPI\Model\MarketOrder;
use IEXBase\TronAPI\Model\NodeHealth;
use IEXBase\TronAPI\Model\TransactionReceipt;
use IEXBase\TronAPI\Model\Witness;
use IEXBase\TronAPI\Transaction\SignatureWeight;
use IEXBase\TronAPI\Value\Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies exact parsing and behavior of native protocol response models.
 */
#[CoversClass(EnergyEstimate::class)]
#[CoversClass(Exchange::class)]
#[CoversClass(MarketOrder::class)]
#[CoversClass(NodeHealth::class)]
#[CoversClass(TransactionReceipt::class)]
#[CoversClass(Witness::class)]
#[CoversClass(SignatureWeight::class)]
final class ProtocolModelTest extends TestCase
{
    private const OWNER_KEY = '0000000000000000000000000000000000000000000000000000000000000001';

    /**
     * Parses native exchange and market records without numeric precision loss.
     */
    public function testExchangeAndMarketModelsRetainExactValues(): void
    {
        $owner = $this->owner();
        $exchangeData = [
            'exchange_id' => 7,
            'creator_address' => $owner->toBase58(),
            'create_time' => 1_700_000_000_000,
            'first_token_id' => bin2hex('_'),
            'first_token_balance' => '9007199254740993',
            'second_token_id' => bin2hex('1002000'),
            'second_token_balance' => '25',
        ];
        $orderData = [
            'order_id' => str_repeat('ab', 32),
            'owner_address' => $owner->toBase58(),
            'create_time' => 1_700_000_000_001,
            'sell_token_id' => bin2hex('_'),
            'sell_token_quantity' => '1000000',
            'buy_token_id' => bin2hex('1002000'),
            'buy_token_quantity' => '25',
            'sell_token_quantity_remain' => '500000',
            'sell_token_quantity_return' => '0',
            'state' => 'ACTIVE',
        ];
        $exchange = Exchange::fromNodeData($exchangeData);
        $order = MarketOrder::fromNodeData($orderData);

        self::assertSame('9007199254740993', $exchange->firstAtomicBalance);
        self::assertTrue($exchange->firstAssetId->isTrx());
        self::assertSame($exchangeData, $exchange->jsonSerialize());
        self::assertSame(str_repeat('ab', 32), $order->id);
        self::assertSame('500000', $order->remainingSellAtomicQuantity);
        self::assertSame($orderData, $order->rawData());
    }

    /**
     * Parses witness counters and arbitrary-precision Energy estimates.
     */
    public function testWitnessAndEnergyModelsUseExactCounters(): void
    {
        $owner = $this->owner();
        $witnessData = [
            'address' => $owner->toBase58(),
            'voteCount' => '9007199254740993',
            'url' => 'https://example.com/sr',
            'totalProduced' => '100',
            'totalMissed' => '2',
            'latestBlockNum' => 99,
            'latestSlotNum' => '9007199254740994',
            'isJobs' => true,
        ];
        $witness = Witness::fromNodeData($witnessData);
        $estimate = EnergyEstimate::fromNodeData(['energy_required' => '9007199254740995']);

        self::assertSame('9007199254740993', $witness->voteCount);
        self::assertTrue($witness->active);
        self::assertSame($witnessData, $witness->jsonSerialize());
        self::assertSame('9007199254740995', $estimate->energyRequired);
    }

    /**
     * Calculates node lag and rejects reversed confirmed state as unhealthy.
     */
    public function testNodeHealthRequiresOrderedBoundedState(): void
    {
        $healthy = new NodeHealth(1_000, 990, 10);
        $reversed = new NodeHealth(990, 1_000, 10);

        self::assertSame(10, $healthy->confirmationLag());
        self::assertTrue($healthy->isHealthy());
        self::assertSame(0, $reversed->confirmationLag());
        self::assertFalse($reversed->isHealthy());
    }

    /**
     * Decodes receipt messages and distinguishes confirmed execution failure.
     */
    public function testReceiptRequiresExplicitSuccessfulExecution(): void
    {
        $success = TransactionReceipt::fromNodeData([
            'id' => str_repeat('cd', 32),
            'blockNumber' => 7,
            'blockTimeStamp' => 1_700_000_000_000,
            'fee' => '1000',
            'receipt' => [
                'result' => 'SUCCESS',
                'energy_usage_total' => '200',
                'energy_penalty_total' => '3',
            ],
        ]);
        self::assertTrue($success->isSuccessful());
        self::assertSame($success, $success->requireSuccessfulExecution());

        $failed = TransactionReceipt::fromNodeData([
            'id' => str_repeat('ef', 32),
            'blockNumber' => 8,
            'blockTimeStamp' => 1_700_000_000_001,
            'receipt' => ['result' => 'REVERT'],
            'resMessage' => bin2hex('permission denied'),
        ]);
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('permission denied');
        $failed->requireSuccessfulExecution();
    }

    /**
     * Parses node-calculated multi-signature weight and rejects duplicate signers.
     */
    public function testSignatureWeightTracksDistinctApprovedSigners(): void
    {
        $owner = $this->owner();
        $permission = [
            'type' => 'Active',
            'id' => 2,
            'permission_name' => 'active',
            'threshold' => 2,
            'keys' => [['address' => $owner->toBase58(), 'weight' => 2]],
            'operations' => str_repeat('ff', 32),
        ];
        $weight = SignatureWeight::fromNodeData([
            'permission' => $permission,
            'current_weight' => 2,
            'approved_list' => [$owner->toBase58()],
        ]);

        self::assertTrue($weight->hasRequiredWeight());
        self::assertCount(1, $weight->approvedSigners());
        self::assertTrue($weight->permission->contains($owner));

        $this->expectException(ResponseDecodingException::class);
        SignatureWeight::fromNodeData([
            'permission' => $permission,
            'approved_list' => [$owner->toBase58(), $owner->toBase58()],
        ]);
    }

    /**
     * Returns the deterministic account used by all native model fixtures.
     */
    private function owner(): Address
    {
        return (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
    }
}
