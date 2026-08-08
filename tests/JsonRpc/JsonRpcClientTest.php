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

namespace IEXBase\TronAPI\Tests\JsonRpc;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Exception\JsonRpcException;
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\JsonRpc\BlockTag;
use IEXBase\TronAPI\JsonRpc\CallBlockReference;
use IEXBase\TronAPI\JsonRpc\JsonRpcClient;
use IEXBase\TronAPI\JsonRpc\JsonRpcParameter;
use IEXBase\TronAPI\JsonRpc\LogFilter;
use IEXBase\TronAPI\JsonRpc\Quantity;
use IEXBase\TronAPI\Tests\Support\HttpResponseFactory;
use IEXBase\TronAPI\Tests\Support\QueueTransport;
use IEXBase\TronAPI\Value\Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies JSON-RPC quantities, envelopes, IDs, filters, and exact balances.
 */
#[CoversClass(JsonRpcClient::class)]
#[CoversClass(JsonRpcException::class)]
#[CoversClass(CallBlockReference::class)]
#[CoversClass(JsonRpcParameter::class)]
#[CoversClass(Quantity::class)]
#[CoversClass(LogFilter::class)]
final class JsonRpcClientTest extends TestCase
{
    private const ACCOUNT = 'TMVQGm1qAQYVdetCeGRRkTWYYrLXuHK2HC';

    /**
     * Preserves integers beyond JavaScript precision and increments request IDs.
     */
    public function testQuantitiesAndSequentialRequestIds(): void
    {
        $transport = new QueueTransport(
            HttpResponseFactory::json(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x10']),
            HttpResponseFactory::json(['jsonrpc' => '2.0', 'id' => 2, 'result' => '0x20000000000001']),
        );
        $client = $this->client($transport);

        self::assertSame('16', $client->blockNumber()->decimal());
        self::assertSame(
            '9007199254740993',
            $client->balance(Address::fromBase58(self::ACCOUNT))->atomicValue(),
        );
        self::assertSame([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'eth_blockNumber',
            'params' => [],
        ], $transport->request(0)->parameters);
        self::assertSame([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'eth_getBalance',
            'params' => [Address::fromBase58(self::ACCOUNT)->toEvmHex(), 'latest'],
        ], $transport->request(1)->parameters);
        self::assertSame('https://rpc.example', $transport->request()->uri);
    }

    /**
     * Parses every node-owned account into the canonical TRON address value.
     */
    public function testNodeOwnedAccountsAreTypedAddresses(): void
    {
        $address = Address::fromBase58(self::ACCOUNT);
        $client = $this->client(new QueueTransport(HttpResponseFactory::json([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [$address->toEvmHex()],
        ])));

        $accounts = $client->accounts();

        self::assertCount(1, $accounts);
        self::assertTrue($address->equals($accounts[0]));
    }

    /**
     * Rejects a response that belongs to another JSON-RPC request.
     */
    public function testMismatchedResponseIdIsRejected(): void
    {
        $client = $this->client(new QueueTransport(HttpResponseFactory::json([
            'jsonrpc' => '2.0',
            'id' => 99,
            'result' => '0x1',
        ])));
        $this->expectException(ResponseDecodingException::class);

        $client->blockNumber();
    }

    /**
     * Preserves the code, message, and data from a valid JSON-RPC error response.
     */
    public function testStructuredJsonRpcErrorIsExposed(): void
    {
        $client = $this->client(new QueueTransport(HttpResponseFactory::json([
            'jsonrpc' => '2.0',
            'id' => 1,
            'error' => [
                'code' => -32602,
                'message' => 'invalid block tag',
                'data' => ['field' => 'block'],
            ],
        ])));

        try {
            $client->blockNumber();
            self::fail('A JSON-RPC error response was returned as a result.');
        } catch (JsonRpcException $exception) {
            self::assertSame(-32602, $exception->rpcCode);
            self::assertSame('invalid block tag', $exception->getMessage());
            self::assertSame(['field' => 'block'], $exception->rpcData);
        }
    }

    /**
     * Sends the current finalized tag for block queries that support it.
     */
    public function testFinalizedBlockTagIsAvailableForBlockQueries(): void
    {
        $transport = new QueueTransport(HttpResponseFactory::json([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => null,
        ]));
        $client = $this->client($transport);

        self::assertNull($client->blockByNumber(BlockTag::Finalized));
        self::assertSame(
            ['finalized', false],
            $transport->request()->parameters['params'],
        );
    }

    /**
     * Keeps the legacy enum case source-compatible without sending an invalid tag.
     */
    public function testPendingBlockTagIsRejectedLocally(): void
    {
        $client = $this->client(new QueueTransport());
        $this->expectException(ValidationException::class);

        $client->blockByNumber(BlockTag::Pending);
    }

    /**
     * Uses eth_call's documented object form without claiming historical execution.
     */
    public function testCallAcceptsNumberAndHashReferenceObjects(): void
    {
        $transport = new QueueTransport(
            HttpResponseFactory::json(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x']),
            HttpResponseFactory::json(['jsonrpc' => '2.0', 'id' => 2, 'result' => '0x']),
        );
        $client = $this->client($transport);
        $hash = str_repeat('ab', 32);

        $client->call([], Quantity::fromDecimal(16));
        $client->call([], CallBlockReference::forHash($hash));

        self::assertSame([[], ['blockNumber' => '0x10']], $transport->request(0)->parameters['params']);
        self::assertSame([[], ['blockHash' => '0x' . $hash]], $transport->request(1)->parameters['params']);
    }

    /**
     * Rejects unsupported historical selectors for state-query methods locally.
     */
    public function testStateReadsAcceptOnlyLatest(): void
    {
        $client = $this->client(new QueueTransport());
        $this->expectException(ValidationException::class);

        $client->balance(Address::fromBase58(self::ACCOUNT), BlockTag::Finalized);
    }

    /**
     * Rejects stateless-only block selectors when creating a stateful filter.
     */
    public function testStatefulFilterRejectsUnsupportedBlockSelectors(): void
    {
        $client = $this->client(new QueueTransport());

        foreach ([
            new LogFilter(BlockTag::Earliest),
            new LogFilter(BlockTag::Finalized),
            new LogFilter(blockHash: str_repeat('ab', 32)),
        ] as $filter) {
            try {
                $client->createLogFilter($filter);
                self::fail('An unsupported eth_newFilter block selector was accepted.');
            } catch (ValidationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * Allows future namespace-style method names while retaining positional parameters.
     */
    public function testGenericRequestAcceptsDottedMethodNames(): void
    {
        $transport = new QueueTransport(HttpResponseFactory::json([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => true,
        ]));
        $client = $this->client($transport);

        self::assertTrue($client->request('vendor.health'));
        self::assertSame('vendor.health', $transport->request()->parameters['method']);
    }

    /**
     * Selects block receipts by a validated 32-byte hash when requested.
     */
    public function testBlockReceiptsCanBeSelectedByHash(): void
    {
        $transport = new QueueTransport(HttpResponseFactory::json([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [],
        ]));
        $hash = str_repeat('ab', 32);
        $client = $this->client($transport);

        self::assertSame([], $client->blockReceiptsByHash($hash));
        self::assertSame(['0x' . $hash], $transport->request()->parameters['params']);
    }

    /**
     * Converts exact decimal quantities to canonical lower-case hexadecimal.
     */
    public function testQuantityEncodingIsCanonical(): void
    {
        $quantity = Quantity::fromDecimal('340282366920938463463374607431768211455');

        self::assertSame('0xffffffffffffffffffffffffffffffff', $quantity->hex());
        self::assertSame('340282366920938463463374607431768211455', $quantity->decimal());
    }

    /**
     * Rejects JSON-RPC quantities that contain redundant leading zeroes.
     */
    public function testNonCanonicalQuantityIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        Quantity::fromHex('0x01');
    }

    /**
     * Builds an address and topic filter with canonical EVM-compatible values.
     */
    public function testLogFilterSerializesStrictFields(): void
    {
        $account = Address::fromBase58(self::ACCOUNT);
        $topic = str_repeat('ab', 32);
        $filter = new LogFilter(
            Quantity::fromDecimal(16),
            BlockTag::Latest,
            [$account],
            [$topic, null],
        );

        self::assertSame([
            'fromBlock' => '0x10',
            'toBlock' => 'latest',
            'address' => $account->toEvmHex(),
            'topics' => ['0x' . $topic, null],
        ], $filter->toArray());
    }

    /**
     * Rejects more topic positions than the JSON-RPC log protocol permits.
     */
    public function testLogFilterEnforcesTopicPositionLimit(): void
    {
        $topic = str_repeat('ab', 32);
        $this->expectException(ValidationException::class);

        new LogFilter(topics: [$topic, $topic, $topic, $topic, $topic]);
    }

    /**
     * Rejects redundant address filters before issuing a remote request.
     */
    public function testLogFilterRejectsDuplicateAddresses(): void
    {
        $account = Address::fromBase58(self::ACCOUNT);
        $this->expectException(ValidationException::class);

        new LogFilter(addresses: [$account, $account]);
    }

    /**
     * Creates a client whose JSON-RPC role is independent from native HTTP nodes.
     */
    private function client(QueueTransport $transport): JsonRpcClient
    {
        $configuration = NodeConfiguration::custom(
            'https://native.example',
            jsonRpcUri: 'https://rpc.example',
        );

        return new JsonRpcClient(new ApiClient($configuration, $transport));
    }
}
