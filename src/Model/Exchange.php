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
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\NativeAssetId;
use JsonSerializable;

/**
 * Represents one protocol-native TRX/TRC-10 bonding-curve exchange.
 */
final readonly class Exchange implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores exchange identity, creator, pair, exact balances, and creation time.
     *
     * @param array<string, mixed> $rawData Complete native exchange response.
     */
    private function __construct(
        public int $id,
        public Address $creatorAddress,
        public int $createdAtMilliseconds,
        public NativeAssetId $firstAssetId,
        public string $firstAtomicBalance,
        public NativeAssetId $secondAssetId,
        public string $secondAtomicBalance,
        array $rawData,
    ) {
        $this->rawData = $rawData;
    }

    /**
     * Creates an exchange from protobuf JSON returned by a native node.
     *
     * @param array<string, mixed> $data Native exchange object.
     */
    public static function fromNodeData(array $data): self
    {
        return new self(
            DataDecoder::integer($data['exchange_id'] ?? null, 'exchange.exchange_id'),
            Address::fromString(DataDecoder::string($data['creator_address'] ?? null, 'exchange.creator_address')),
            DataDecoder::integer($data['create_time'] ?? null, 'exchange.create_time'),
            NativeAssetId::fromNodeHex(DataDecoder::string($data['first_token_id'] ?? null, 'exchange.first_token_id')),
            DataDecoder::unsignedDecimal($data['first_token_balance'] ?? 0, 'exchange.first_token_balance'),
            NativeAssetId::fromNodeHex(DataDecoder::string($data['second_token_id'] ?? null, 'exchange.second_token_id')),
            DataDecoder::unsignedDecimal($data['second_token_balance'] ?? 0, 'exchange.second_token_balance'),
            $data,
        );
    }

    /**
     * Returns the untouched exchange response.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless exchange response.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
