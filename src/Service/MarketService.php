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

namespace IEXBase\TronAPI\Service;

use IEXBase\TronAPI\Api\ApiClient;
use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Enum\ConfirmationLevel;
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Model\MarketOrder;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\ByteString;
use IEXBase\TronAPI\Value\Memo;
use IEXBase\TronAPI\Value\NativeAssetAmount;
use IEXBase\TronAPI\Value\NativeAssetId;

/**
 * Reads and operates the protocol-native TRX/TRC-10 limit-order market.
 */
final readonly class MarketService
{
    /**
     * Creates the service over native nodes and the shared transaction factory.
     */
    public function __construct(
        private ApiClient $client,
        private TransactionFactory $transactions,
    ) {
    }

    /**
     * Builds a limit order with exact sell and minimum buy quantities.
     */
    public function createOrder(
        Address $ownerAddress,
        NativeAssetAmount $sell,
        NativeAssetAmount $buy,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        if ($sell->assetId->equals($buy->assetId)) {
            throw new ValidationException(
                'A market order must sell and buy different assets.',
            );
        }
        $fields = [
            'sell_token_id' => $sell->assetId->value(),
            'sell_token_quantity' => $sell->amount->atomicInteger(),
            'buy_token_id' => $buy->assetId->value(),
            'buy_token_quantity' => $buy->amount->atomicInteger(),
        ];

        return $this->transactions->createNativeContract(
            Endpoint::CreateMarketOrder,
            'MarketSellAssetContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }

    /**
     * Builds a transaction that cancels one exact 32-byte native order ID.
     */
    public function cancelOrder(
        Address $ownerAddress,
        string $orderId,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        $orderId = Hex::canonicalize($orderId, 32);

        return $this->transactions->createNativeContract(
            Endpoint::CancelMarketOrder,
            'MarketCancelOrderContract',
            $ownerAddress,
            ['order_id' => ByteString::fromHex($orderId)],
            $memo,
            $permissionId,
            ['order_id' => $orderId],
        );
    }

    /**
     * Returns one native order by ID, or null when absent.
     */
    public function find(
        string $orderId,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): ?MarketOrder {
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetMarketOrder,
            Endpoint::GetConfirmedMarketOrder,
        );
        $data = $this->client->request($endpoint->request([
            'value' => Hex::canonicalize($orderId, 32),
            'visible' => true,
        ]))->data();

        return $data === [] ? null : MarketOrder::fromNodeData(DataDecoder::object($data, 'market order'));
    }

    /**
     * Returns the account's exact order IDs and native active/total counters.
     *
     * @return array{orderIds: list<string>, activeCount: int, totalCount: int}
     */
    public function accountOrderIndex(
        Address $address,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        $endpoint = Endpoint::forConfirmation(
            $level,
            Endpoint::GetMarketOrdersByAccount,
            Endpoint::GetConfirmedMarketOrdersByAccount,
        );
        $response = $this->client->request($endpoint->request([
            'value' => $address->toBase58(),
            'visible' => true,
        ]));
        $ids = $response->value('orders') ?? [];
        if (!is_array($ids) || !array_is_list($ids)) {
            throw new ResponseDecodingException('Market account order IDs must be a list.');
        }

        return [
            'orderIds' => array_map(
                static fn (mixed $id): string => Hex::canonicalize(
                    DataDecoder::string($id, 'orders[]'),
                    32,
                ),
                $ids,
            ),
            'activeCount' => DataDecoder::integer($response->value('count') ?? 0, 'count'),
            'totalCount' => DataDecoder::integer($response->value('total_count') ?? 0, 'total_count'),
        ];
    }

    /**
     * Returns all current orders for one directed asset pair.
     *
     * @return list<MarketOrder>
     */
    public function orders(
        NativeAssetId $sellAssetId,
        NativeAssetId $buyAssetId,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        return array_map(
            static fn (array $data): MarketOrder => MarketOrder::fromNodeData($data),
            DataDecoder::objectList($this->pairValue(
                $sellAssetId,
                $buyAssetId,
                $level,
                Endpoint::GetMarketOrdersByPair,
                Endpoint::GetConfirmedMarketOrdersByPair,
                'orders',
            ), 'orders'),
        );
    }

    /**
     * Returns every directed market pair known to the selected node state.
     *
     * @return list<array{sellAssetId: NativeAssetId, buyAssetId: NativeAssetId}>
     */
    public function pairs(ConfirmationLevel $level = ConfirmationLevel::Confirmed): array
    {
        $endpoint = Endpoint::forConfirmation($level, Endpoint::GetMarketPairs, Endpoint::GetConfirmedMarketPairs);
        $response = $this->client->request($endpoint->request());

        return array_map(
            static fn (array $pair): array => [
                'sellAssetId' => NativeAssetId::fromNodeHex(
                    DataDecoder::string($pair['sell_token_id'] ?? null, 'orderPair.sell_token_id'),
                ),
                'buyAssetId' => NativeAssetId::fromNodeHex(
                    DataDecoder::string($pair['buy_token_id'] ?? null, 'orderPair.buy_token_id'),
                ),
            ],
            DataDecoder::objectList($response->value('orderPair') ?? [], 'orderPair'),
        );
    }

    /**
     * Returns the exact native price levels for one directed asset pair.
     *
     * @return list<array{sellAtomicQuantity: string, buyAtomicQuantity: string}>
     */
    public function prices(
        NativeAssetId $sellAssetId,
        NativeAssetId $buyAssetId,
        ConfirmationLevel $level = ConfirmationLevel::Confirmed,
    ): array {
        return array_map(
            static fn (array $price): array => [
                'sellAtomicQuantity' => DataDecoder::unsignedDecimal(
                    $price['sell_token_quantity'] ?? 0,
                    'prices.sell_token_quantity',
                ),
                'buyAtomicQuantity' => DataDecoder::unsignedDecimal(
                    $price['buy_token_quantity'] ?? 0,
                    'prices.buy_token_quantity',
                ),
            ],
            DataDecoder::objectList($this->pairValue(
                $sellAssetId,
                $buyAssetId,
                $level,
                Endpoint::GetMarketPrices,
                Endpoint::GetConfirmedMarketPrices,
                'prices',
            ), 'prices'),
        );
    }

    /**
     * Returns one repeated value for a directed pair at the requested state level.
     */
    private function pairValue(
        NativeAssetId $sellAssetId,
        NativeAssetId $buyAssetId,
        ConfirmationLevel $level,
        Endpoint $latestEndpoint,
        Endpoint $confirmedEndpoint,
        string $field,
    ): mixed {
        $endpoint = Endpoint::forConfirmation($level, $latestEndpoint, $confirmedEndpoint);

        return $this->client->request($endpoint->request(
            $this->pairFields($sellAssetId, $buyAssetId),
        ))->value($field) ?? [];
    }

    /**
     * Returns request fields for one directed pair and rejects identical assets.
     *
     * @return array{sell_token_id: string, buy_token_id: string, visible: true}
     */
    private function pairFields(NativeAssetId $sellAssetId, NativeAssetId $buyAssetId): array
    {
        if ($sellAssetId->equals($buyAssetId)) {
            throw new ValidationException(
                'A market pair requires two different asset identifiers.',
            );
        }

        return [
            'sell_token_id' => $sellAssetId->value(),
            'buy_token_id' => $buyAssetId->value(),
            'visible' => true,
        ];
    }
}
