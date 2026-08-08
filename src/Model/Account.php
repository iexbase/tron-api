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
use IEXBase\TronAPI\Exception\ResponseDecodingException;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\NativeAssetId;
use JsonSerializable;

/**
 * Represents a native account response while retaining uncommon protocol fields.
 */
final readonly class Account implements JsonSerializable
{
    /** @var list<AccountAssetBalance> */
    private array $assetBalances;

    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores exact balances, timestamps, and the original decoded account object.
     *
     * @param list<AccountAssetBalance> $assetBalances Ordered exact TRC-10 balances.
     * @param array<string, mixed>      $rawData Complete native node response.
     */
    private function __construct(
        public Address $address,
        public Amount $balance,
        public ?int $createdAtMilliseconds,
        public ?int $lastOperationAtMilliseconds,
        array $assetBalances,
        array $rawData,
    ) {
        $this->assetBalances = $assetBalances;
        $this->rawData = $rawData;
    }

    /**
     * Creates an account from a FullNode or SolidityNode response.
     *
     * @param array<string, mixed> $data Decoded account object.
     */
    public static function fromNodeData(array $data): self
    {
        if ($data === []) {
            throw new ResponseDecodingException('The requested account does not exist or has not been activated.');
        }

        $address = Address::fromString(DataDecoder::string($data['address'] ?? null, 'address'));
        $balance = Amount::fromAtomic(DataDecoder::unsignedDecimal($data['balance'] ?? 0, 'balance'));
        $assets = self::readAssetBalances($data['assetV2'] ?? []);

        return new self(
            $address,
            $balance,
            self::optionalTimestamp($data['create_time'] ?? null, 'create_time'),
            self::optionalTimestamp($data['latest_opration_time'] ?? null, 'latest_opration_time'),
            $assets,
            $data,
        );
    }

    /**
     * Returns exact TRC-10 atomic balances in the node's response order.
     *
     * Token precision is deliberately not guessed; callers must combine these
     * atomic values with the corresponding asset metadata before formatting.
     *
     * @return list<AccountAssetBalance>
     */
    public function assetBalances(): array
    {
        return $this->assetBalances;
    }

    /**
     * Returns one exact TRC-10 balance by typed identifier, or null when absent.
     */
    public function assetBalance(NativeAssetId $assetId): ?AccountAssetBalance
    {
        foreach ($this->assetBalances as $balance) {
            if ($balance->assetId->equals($assetId)) {
                return $balance;
            }
        }

        return null;
    }

    /**
     * Returns the untouched account object for protocol fields not modeled yet.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless node object.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }

    /**
     * Parses repeated TRC-10 balance records without float conversion.
     *
     * @return list<AccountAssetBalance>
     */
    private static function readAssetBalances(mixed $value): array
    {
        $balances = [];
        foreach (DataDecoder::objectList($value, 'assetV2') as $position => $asset) {
            $key = DataDecoder::string($asset['key'] ?? null, sprintf('assetV2[%d].key', $position));
            $identity = 'asset:' . $key;
            if ($key === '' || isset($balances[$identity])) {
                throw new ResponseDecodingException('TRC-10 account balances contain an empty or duplicate token ID.');
            }
            $balances[$identity] = new AccountAssetBalance(
                NativeAssetId::fromString($key),
                DataDecoder::unsignedDecimal(
                    $asset['value'] ?? null,
                    sprintf('assetV2[%d].value', $position),
                ),
            );
        }

        return array_values($balances);
    }

    /**
     * Parses an optional millisecond timestamp that fits the host platform.
     */
    private static function optionalTimestamp(mixed $value, string $field): ?int
    {
        return $value === null ? null : DataDecoder::integer($value, $field);
    }
}
