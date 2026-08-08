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

namespace IEXBase\TronAPI\Model;

use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\NativeAssetId;
use JsonSerializable;

/**
 * Represents one native limit order with exact quantities and lifecycle state.
 */
final readonly class MarketOrder implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores order identity, pair, quantities, remaining amount, returns, and state.
     *
     * @param array<string, mixed> $rawData Complete native order response.
     */
    private function __construct(
        public string $id,
        public Address $ownerAddress,
        public int $createdAtMilliseconds,
        public NativeAssetId $sellAssetId,
        public string $sellAtomicQuantity,
        public NativeAssetId $buyAssetId,
        public string $minimumBuyAtomicQuantity,
        public string $remainingSellAtomicQuantity,
        public string $returnedSellAtomicQuantity,
        public string $state,
        array $rawData,
    ) {
        $this->rawData = $rawData;
    }

    /**
     * Creates a limit order from protobuf JSON returned by a native node.
     *
     * @param array<string, mixed> $data Native market order object.
     */
    public static function fromNodeData(array $data): self
    {
        return new self(
            Hex::canonicalize(DataDecoder::string($data['order_id'] ?? null, 'order.order_id'), 32),
            Address::fromString(DataDecoder::string($data['owner_address'] ?? null, 'order.owner_address')),
            DataDecoder::integer($data['create_time'] ?? null, 'order.create_time'),
            NativeAssetId::fromNodeHex(DataDecoder::string($data['sell_token_id'] ?? null, 'order.sell_token_id')),
            DataDecoder::unsignedDecimal($data['sell_token_quantity'] ?? 0, 'order.sell_token_quantity'),
            NativeAssetId::fromNodeHex(DataDecoder::string($data['buy_token_id'] ?? null, 'order.buy_token_id')),
            DataDecoder::unsignedDecimal($data['buy_token_quantity'] ?? 0, 'order.buy_token_quantity'),
            DataDecoder::unsignedDecimal($data['sell_token_quantity_remain'] ?? 0, 'order.sell_token_quantity_remain'),
            DataDecoder::unsignedDecimal($data['sell_token_quantity_return'] ?? 0, 'order.sell_token_quantity_return'),
            DataDecoder::string($data['state'] ?? 'ACTIVE', 'order.state'),
            $data,
        );
    }

    /**
     * Returns the untouched market order response.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless market order response.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
