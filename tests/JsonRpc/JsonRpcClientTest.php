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
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\JsonRpc\BlockTag;
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
            $client->balance(Address::fromBase58(self::ACCOUNT), BlockTag::Latest)->atomicValue(),
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
        self::assertSame('https://rpc.example/jsonrpc', $transport->request()->uri);
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
