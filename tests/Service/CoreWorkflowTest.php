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

namespace IEXBase\TronAPI\Tests\Service;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Enum\Network;
use IEXBase\TronAPI\Exception\HttpException;
use IEXBase\TronAPI\Exception\TransactionException;
use IEXBase\TronAPI\Indexer\TronGridProvider;
use IEXBase\TronAPI\Model\Account;
use IEXBase\TronAPI\Model\AccountAssetBalance;
use IEXBase\TronAPI\Model\Block;
use IEXBase\TronAPI\Model\BroadcastResult;
use IEXBase\TronAPI\Service\AccountService;
use IEXBase\TronAPI\Service\BlockService;
use IEXBase\TronAPI\Service\NetworkService;
use IEXBase\TronAPI\Service\TransactionService;
use IEXBase\TronAPI\Service\TransferService;
use IEXBase\TronAPI\Tests\Support\HttpResponseFactory;
use IEXBase\TronAPI\Tests\Support\QueueTransport;
use IEXBase\TronAPI\Tests\Support\TransactionFixture;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionCostCalculator;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Transaction\TransactionIntent;
use IEXBase\TronAPI\Transaction\TransactionSigner;
use IEXBase\TronAPI\Tron;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;
use IEXBase\TronAPI\Value\NativeAssetId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies typed native workflows and provider-independent facade composition.
 */
#[CoversClass(Tron::class)]
#[CoversClass(AccountService::class)]
#[CoversClass(BlockService::class)]
#[CoversClass(NetworkService::class)]
#[CoversClass(TransactionService::class)]
#[CoversClass(TransferService::class)]
#[CoversClass(TransactionFactory::class)]
#[CoversClass(TransactionCostCalculator::class)]
#[CoversClass(Account::class)]
#[CoversClass(AccountAssetBalance::class)]
#[CoversClass(Block::class)]
#[CoversClass(BroadcastResult::class)]
final class CoreWorkflowTest extends TestCase
{
    private const OWNER_KEY = '0000000000000000000000000000000000000000000000000000000000000001';
    private const RECIPIENT = 'TPL66VK2gCXNCD7EJg9pgJRfqcRazjhUZY';

    /**
     * Builds a transfer through the node and checks every security-managed request field.
     */
    public function testTransferFactoryBuildsAndVerifiesExactRequest(): void
    {
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $recipient = Address::fromBase58(self::RECIPIENT);
        $amount = Amount::fromDecimal('1.25');
        $memo = Memo::fromText('invoice-42');
        $intent = new TransactionIntent(
            'TransferContract',
            $owner,
            ['to_address' => $recipient, 'amount' => 1_250_000],
            2,
            $memo->toHex(),
        );
        $transport = new QueueTransport(HttpResponseFactory::json(TransactionFixture::data($intent)));
        $service = new TransferService(new TransactionFactory($this->client($transport)));

        $transaction = $service->createTrxTransfer($owner, $recipient, $amount, $memo, 2);
        $request = $transport->request();

        self::assertSame(Endpoint::CreateTransaction->value, parse_url($request->uri, PHP_URL_PATH));
        self::assertSame($owner->toBase58(), $request->parameters['owner_address']);
        self::assertSame($recipient->toBase58(), $request->parameters['to_address']);
        self::assertSame(1_250_000, $request->parameters['amount']);
        self::assertSame(2, $request->parameters['Permission_id']);
        self::assertSame($memo->text(), $request->parameters['extra_data']);
        self::assertSame($intent->contractType, $transaction->singleContract()['type']);
        self::assertSame($intent->contractType, $transaction->approvedIntent()->contractType);
        self::assertTrue((new TransactionSigner())->appendSignature(
            $transaction,
            new LocalPrivateKeySigner(self::OWNER_KEY),
        )->isSigned());
    }

    /**
     * Prevents a service from overriding owner or other factory-managed fields.
     */
    public function testFactoryRejectsReservedRequestFields(): void
    {
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $factory = new TransactionFactory($this->client(new QueueTransport()));
        $intent = new TransactionIntent('TransferContract', $owner, [
            'to_address' => Address::fromBase58(self::RECIPIENT),
            'amount' => 1,
        ]);
        $this->expectException(TransactionException::class);

        $factory->create(Endpoint::CreateTransaction, $intent, ['owner_address' => self::RECIPIENT]);
    }

    /**
     * Parses account monetary fields as exact decimal strings from confirmed state.
     */
    public function testConfirmedAccountPreservesLargeBalances(): void
    {
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $transport = new QueueTransport(HttpResponseFactory::json([
            'address' => $owner->toBase58(),
            'balance' => '9007199254740993000000',
            'create_time' => 1_700_000_000_000,
            'assetV2' => [['key' => '1002000', 'value' => '18446744073709551615']],
        ]));
        $api = $this->client($transport);
        $account = (new AccountService($api, new TransactionFactory($api)))->get($owner);

        self::assertInstanceOf(Account::class, $account);
        $assetBalance = $account->assetBalance(NativeAssetId::fromString('1002000'));
        self::assertInstanceOf(AccountAssetBalance::class, $assetBalance);
        self::assertSame('9007199254740993000000', $account->balance->atomicValue());
        self::assertSame('18446744073709551615', $assetBalance->atomicValue());
        self::assertSame('/walletsolidity/getaccount', parse_url($transport->request()->uri, PHP_URL_PATH));
    }

    /**
     * Selects the correct node role and visible account ID representation.
     */
    public function testLatestAccountCanBeReadByPermanentId(): void
    {
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $transport = new QueueTransport(HttpResponseFactory::json([
            'address' => $owner->toBase58(),
            'account_id' => 'merchant42',
            'balance' => 1,
        ]));
        $api = $this->client($transport);
        $account = (new AccountService($api, new TransactionFactory($api)))->getById(
            'merchant42',
            ConfirmationLevel::Latest,
        );

        self::assertInstanceOf(Account::class, $account);
        self::assertSame('/wallet/getaccountbyid', parse_url($transport->request()->uri, PHP_URL_PATH));
        self::assertSame([
            'account_id' => 'merchant42',
            'visible' => true,
        ], $transport->request()->parameters);
    }

    /**
     * Uses the FullNode resource route because java-tron has no SolidityNode equivalent.
     */
    public function testAccountResourcesUseTheSupportedFullNodeRoute(): void
    {
        $owner = (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
        $transport = new QueueTransport(HttpResponseFactory::json([
            'EnergyLimit' => 100,
            'EnergyUsed' => 25,
        ]));
        $api = $this->client($transport);
        $resources = (new AccountService($api, new TransactionFactory($api)))->resources($owner);

        self::assertSame(100, $resources['EnergyLimit']);
        self::assertSame('/wallet/getaccountresource', parse_url($transport->request()->uri, PHP_URL_PATH));
        self::assertSame([
            'address' => $owner->toBase58(),
            'visible' => true,
        ], $transport->request()->parameters);
    }

    /**
     * Retains confirmation state and native transaction order when parsing blocks.
     */
    public function testLatestBlockModelUsesSelectedNodeRole(): void
    {
        $transport = new QueueTransport(HttpResponseFactory::json([
            'blockID' => str_repeat('ab', 32),
            'block_header' => ['raw_data' => ['number' => 77, 'timestamp' => 1_700_000_001_000]],
            'transactions' => [['txID' => str_repeat('cd', 32)]],
        ]));
        $block = (new BlockService($this->client($transport)))->latest(ConfirmationLevel::Latest);

        self::assertSame(77, $block->number);
        self::assertFalse($block->confirmed);
        self::assertCount(1, $block->transactions());
        self::assertSame('/wallet/getnowblock', parse_url($transport->request()->uri, PHP_URL_PATH));
    }

    /**
     * Broadcasts only a locally signed transaction and verifies the returned identifier.
     */
    public function testSignedTransactionBroadcastVerifiesResponseId(): void
    {
        $transaction = $this->signedTransfer();
        $transport = new QueueTransport(HttpResponseFactory::json([
            'result' => true,
            'txid' => $transaction->id(),
        ]));
        $result = (new TransactionService($this->client($transport)))->broadcast($transaction);

        self::assertSame($transaction->id(), $result->transactionId);
        self::assertSame('/wallet/broadcasttransaction', parse_url($transport->request()->uri, PHP_URL_PATH));
        self::assertNotEmpty($transport->request()->parameters['signature']);
    }

    /**
     * Does not retry an ambiguous broadcast failure after a node may have accepted the transaction.
     */
    public function testBroadcastTransportFailureIsNotRetried(): void
    {
        $transaction = $this->signedTransfer();
        $transport = new QueueTransport(
            HttpResponseFactory::json(['error' => 'ambiguous upstream failure'], 500),
            HttpResponseFactory::json(['result' => true, 'txid' => $transaction->id()]),
        );

        try {
            (new TransactionService($this->client($transport)))->broadcast($transaction);
            self::fail('An ambiguous broadcast failure must be reported to the caller.');
        } catch (HttpException) {
            self::assertCount(1, $transport->requests());
        }
    }

    /**
     * Returns signed and arbitrarily large chain parameters without float conversion.
     */
    public function testChainParametersUseExactSignedDecimalText(): void
    {
        $transport = new QueueTransport(HttpResponseFactory::json([
            'chainParameter' => [
                ['key' => 'getMaintenanceTimeInterval', 'value' => 21_600_000],
                ['key' => 'customSignedParameter', 'value' => '-9007199254740993'],
            ],
        ]));
        $api = $this->client($transport);
        $service = new NetworkService($api, new BlockService($api));

        self::assertSame([
            'getMaintenanceTimeInterval' => '21600000',
            'customSignedParameter' => '-9007199254740993',
        ], $service->chainParameters());
    }

    /**
     * Calculates exact resource unit prices and pre-broadcast transaction Bandwidth.
     */
    public function testTransactionBandwidthAndMaximumBurnAreCalculatedExactly(): void
    {
        $intent = new TransactionIntent(
            'TransferContract',
            (new LocalPrivateKeySigner(self::OWNER_KEY))->address(),
            ['to_address' => Address::fromBase58(self::RECIPIENT), 'amount' => 1],
        );
        $transaction = TransactionFixture::transaction($intent);
        $parameters = ['chainParameter' => [
            ['key' => 'getTransactionFee', 'value' => 1_000],
            ['key' => 'getEnergyFee', 'value' => 100],
        ]];
        $transport = new QueueTransport(
            HttpResponseFactory::json($parameters),
            HttpResponseFactory::json($parameters),
        );
        $api = $this->client($transport);
        $network = new NetworkService($api, new BlockService($api));
        $transactions = new TransactionService($api);
        $unitPrice = $network->bandwidthUnitPrice();
        $energyUnitPrice = $network->energyUnitPrice();
        $bandwidth = $transactions->estimateBandwidth($transaction, 2);

        self::assertSame(intdiv(strlen($transaction->rawDataHex()), 2) + 3 + 64 + (67 * 2), $bandwidth);
        self::assertSame('0.001', $unitPrice->decimalValue());
        self::assertSame('0.0001', $energyUnitPrice->decimalValue());
        self::assertSame(
            (string) ($bandwidth * 1_000),
            $transactions->maximumBandwidthBurn($transaction, $unitPrice, 2)->atomicValue(),
        );
    }

    /**
     * Rejects a final signature count that cannot produce a valid TRON transaction.
     */
    public function testTransactionBandwidthRejectsAnInvalidSignatureCount(): void
    {
        $intent = new TransactionIntent(
            'TransferContract',
            (new LocalPrivateKeySigner(self::OWNER_KEY))->address(),
            ['to_address' => Address::fromBase58(self::RECIPIENT), 'amount' => 1],
        );
        $transaction = TransactionFixture::transaction($intent);
        $this->expectException(TransactionException::class);

        (new TransactionService($this->client(new QueueTransport())))
            ->estimateBandwidth($transaction, 0);
    }

    /**
     * Leaves custom node deployments free of any implicit TronGrid dependency.
     */
    public function testCustomFacadeDoesNotInstallIndexerProviders(): void
    {
        $tron = Tron::create(NodeConfiguration::custom('http://localhost:8090'), new QueueTransport());

        self::assertNull($tron->accountHistory());
        self::assertNull($tron->events());
        self::assertNull($tron->tokenIndex());
    }

    /**
     * Installs the replaceable TronGrid adapter only for explicit public profiles.
     */
    public function testPublicFacadeProvidesDefaultIndexerAdapter(): void
    {
        $tron = Tron::create(NodeConfiguration::forNetwork(Network::Shasta), new QueueTransport());

        self::assertInstanceOf(TronGridProvider::class, $tron->accountHistory());
        self::assertInstanceOf(TronGridProvider::class, $tron->events());
        self::assertInstanceOf(TronGridProvider::class, $tron->tokenIndex());
    }

    /**
     * Creates a deterministic custom API client for isolated service tests.
     */
    private function client(QueueTransport $transport): ApiClient
    {
        return new ApiClient(NodeConfiguration::custom('https://node.example'), $transport);
    }

    /**
     * Creates one locally signed deterministic transfer for broadcast behavior tests.
     */
    private function signedTransfer(): Transaction
    {
        $signer = new LocalPrivateKeySigner(self::OWNER_KEY);
        $intent = new TransactionIntent('TransferContract', $signer->address(), [
            'to_address' => Address::fromBase58(self::RECIPIENT),
            'amount' => 1,
        ]);

        return (new TransactionSigner())->appendSignature(
            TransactionFixture::transaction($intent),
            $signer,
            $intent,
        );
    }
}
