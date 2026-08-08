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
use IEXBase\TronAPI\Configuration\NodeConfiguration;
use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Http\HttpRequest;
use IEXBase\TronAPI\Http\HttpResponse;
use IEXBase\TronAPI\Model\Asset;
use IEXBase\TronAPI\Model\Exchange;
use IEXBase\TronAPI\Model\MarketOrder;
use IEXBase\TronAPI\Service\ExchangeService;
use IEXBase\TronAPI\Service\MarketService;
use IEXBase\TronAPI\Tests\Support\HttpResponseFactory;
use IEXBase\TronAPI\Tests\Support\QueueTransport;
use IEXBase\TronAPI\Tests\Support\TransactionFixture;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Transaction\TransactionIntent;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;
use IEXBase\TronAPI\Value\NativeAssetAmount;
use IEXBase\TronAPI\Value\NativeAssetId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verifies native exchange pools and the TRX/TRC-10 limit-order market.
 */
#[CoversClass(ExchangeService::class)]
#[CoversClass(MarketService::class)]
#[CoversClass(Exchange::class)]
#[CoversClass(MarketOrder::class)]
final class NativeMarketWorkflowTest extends TestCase
{
    private const OWNER_KEY = '0000000000000000000000000000000000000000000000000000000000000001';
    private const ORDER_ID = 'abababababababababababababababababababababababababababababababab';

    /**
     * Reads exchange lists, individual pools, and bounded native pages.
     */
    public function testExchangeQueriesReturnTypedPools(): void
    {
        $data = $this->exchangeData();
        $transport = new QueueTransport(
            HttpResponseFactory::json(['exchanges' => [$data]]),
            HttpResponseFactory::json($data),
            HttpResponseFactory::json(['exchanges' => [$data]]),
        );
        $service = $this->exchangeService($transport);

        $all = $service->list();
        $one = $service->find(7);
        $page = $service->page(10, 5);

        self::assertCount(1, $all);
        self::assertSame(7, $one?->id);
        self::assertCount(1, $page);
        self::assertTrue($all[0]->firstAssetId->isTrx());
        self::assertSame(['offset' => 10, 'limit' => 5], $transport->request(2)->parameters);
    }

    /**
     * Builds create, inject, withdraw, and trade exchange transactions exactly.
     */
    public function testExchangeMutationsShareOneVerifiedFieldSchema(): void
    {
        $owner = $this->owner();
        $trx = NativeAssetAmount::trx(Amount::fromDecimal('10'));
        $token = NativeAssetAmount::trc10($this->asset(), Amount::fromDecimal('20.000', 3));
        $createFields = [
            'first_token_id' => '_',
            'first_token_balance' => 10_000_000,
            'second_token_id' => '1002000',
            'second_token_balance' => 20_000,
        ];
        $liquidityFields = [
            'exchange_id' => 7,
            'token_id' => '1002000',
            'quant' => 20_000,
        ];
        $tradeFields = [
            'exchange_id' => 7,
            'token_id' => '_',
            'quant' => 10_000_000,
            'expected' => 20_000,
        ];
        $transport = new QueueTransport(
            $this->transactionResponse(new TransactionIntent('ExchangeCreateContract', $owner, $createFields)),
            $this->transactionResponse(new TransactionIntent('ExchangeInjectContract', $owner, $liquidityFields)),
            $this->transactionResponse(new TransactionIntent('ExchangeWithdrawContract', $owner, $liquidityFields)),
            $this->transactionResponse(new TransactionIntent('ExchangeTransactionContract', $owner, $tradeFields)),
        );
        $service = $this->exchangeService($transport);

        $service->create($owner, $trx, $token);
        $service->inject($owner, 7, $token);
        $service->withdraw($owner, 7, $token);
        $service->trade($owner, 7, $trx, $token);

        self::assertSame([
            '/wallet/exchangecreate',
            '/wallet/exchangeinject',
            '/wallet/exchangewithdraw',
            '/wallet/exchangetransaction',
        ], $this->requestPaths($transport));
    }

    /**
     * Reads every native market query shape through typed identifiers and orders.
     */
    public function testMarketQueriesDecodeAllNativeResponseShapes(): void
    {
        $order = $this->orderData();
        $transport = new QueueTransport(
            HttpResponseFactory::json($order),
            HttpResponseFactory::json([
                'orders' => [self::ORDER_ID],
                'count' => 1,
                'total_count' => 3,
            ]),
            HttpResponseFactory::json(['orders' => [$order]]),
            HttpResponseFactory::json(['orderPair' => [[
                'sell_token_id' => bin2hex('_'),
                'buy_token_id' => bin2hex('1002000'),
            ]]]),
            HttpResponseFactory::json(['prices' => [[
                'sell_token_quantity' => '1000000',
                'buy_token_quantity' => '2000',
            ]]]),
        );
        $service = $this->marketService($transport);
        $trx = NativeAssetId::trx();
        $token = NativeAssetId::trc10($this->asset());

        self::assertSame(self::ORDER_ID, $service->find(self::ORDER_ID)?->id);
        self::assertSame(3, $service->accountOrderIndex($this->owner())['totalCount']);
        self::assertCount(1, $service->orders($trx, $token));
        self::assertSame('1002000', $service->pairs()[0]['buyAssetId']->value());
        self::assertSame('2000', $service->prices($trx, $token)[0]['buyAtomicQuantity']);
    }

    /**
     * Builds and verifies native market create and cancellation transactions.
     */
    public function testMarketMutationsKeepExactOrderIdentity(): void
    {
        $owner = $this->owner();
        $sell = NativeAssetAmount::trx(Amount::fromDecimal('1'));
        $buy = NativeAssetAmount::trc10($this->asset(), Amount::fromDecimal('2.000', 3));
        $transport = new QueueTransport(
            $this->transactionResponse(new TransactionIntent('MarketSellAssetContract', $owner, [
                'sell_token_id' => '_',
                'sell_token_quantity' => 1_000_000,
                'buy_token_id' => '1002000',
                'buy_token_quantity' => 2_000,
            ])),
            $this->transactionResponse(new TransactionIntent('MarketCancelOrderContract', $owner, [
                'order_id' => ByteString::fromHex(self::ORDER_ID),
            ])),
        );
        $service = $this->marketService($transport);

        $service->createOrder($owner, $sell, $buy);
        $service->cancelOrder($owner, self::ORDER_ID);

        self::assertSame(1_000_000, $transport->request()->parameters['sell_token_quantity']);
        self::assertSame(self::ORDER_ID, $transport->request(1)->parameters['order_id']);
    }

    /**
     * Creates deterministic TRC-10 metadata used by market amount values.
     */
    private function asset(): Asset
    {
        return Asset::fromNodeData([
            'id' => '1002000',
            'name' => 'Example Token',
            'abbr' => 'EXT',
            'owner_address' => $this->owner()->toBase58(),
            'precision' => 3,
            'total_supply' => '1000000',
            'start_time' => 1,
            'end_time' => 2,
        ]);
    }

    /**
     * Returns one complete native exchange response fixture.
     *
     * @return array<string, mixed>
     */
    private function exchangeData(): array
    {
        return [
            'exchange_id' => 7,
            'creator_address' => $this->owner()->toBase58(),
            'create_time' => 1_700_000_000_000,
            'first_token_id' => bin2hex('_'),
            'first_token_balance' => '10000000',
            'second_token_id' => bin2hex('1002000'),
            'second_token_balance' => '20000',
        ];
    }

    /**
     * Returns one complete native market-order response fixture.
     *
     * @return array<string, mixed>
     */
    private function orderData(): array
    {
        return [
            'order_id' => self::ORDER_ID,
            'owner_address' => $this->owner()->toBase58(),
            'create_time' => 1_700_000_000_000,
            'sell_token_id' => bin2hex('_'),
            'sell_token_quantity' => '1000000',
            'buy_token_id' => bin2hex('1002000'),
            'buy_token_quantity' => '2000',
            'sell_token_quantity_remain' => '500000',
            'sell_token_quantity_return' => '0',
            'state' => 'ACTIVE',
        ];
    }

    /**
     * Encodes a matching native transaction response.
     */
    private function transactionResponse(TransactionIntent $intent): HttpResponse
    {
        return HttpResponseFactory::json(TransactionFixture::data($intent));
    }

    /**
     * Returns validated URI paths for all recorded native requests.
     *
     * @return list<string>
     */
    private function requestPaths(QueueTransport $transport): array
    {
        return array_map(static function (HttpRequest $request): string {
            $path = parse_url($request->uri, PHP_URL_PATH);
            if (!is_string($path)) {
                throw new RuntimeException('A native test request contains an invalid URI.');
            }

            return $path;
        }, $transport->requests());
    }

    /**
     * Creates an exchange service over a deterministic transport.
     */
    private function exchangeService(QueueTransport $transport): ExchangeService
    {
        $api = $this->api($transport);

        return new ExchangeService($api, new TransactionFactory($api));
    }

    /**
     * Creates a market service over a deterministic transport.
     */
    private function marketService(QueueTransport $transport): MarketService
    {
        $api = $this->api($transport);

        return new MarketService($api, new TransactionFactory($api));
    }

    /**
     * Creates a role-aware native API client over one queued transport.
     */
    private function api(QueueTransport $transport): ApiClient
    {
        return new ApiClient(NodeConfiguration::custom('https://node.example'), $transport);
    }

    /**
     * Returns the deterministic owner used in native market fixtures.
     */
    private function owner(): Address
    {
        return (new LocalPrivateKeySigner(self::OWNER_KEY))->address();
    }
}
