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
use JsonSerializable;

/**
 * Represents TRC-10 metadata with its own precision and exact total supply.
 */
final readonly class Asset implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores typed TRC-10 identity, issuer, precision, supply, and schedule.
     *
     * @param array<string, mixed> $rawData Complete asset response.
     */
    private function __construct(
        public string $id,
        public string $name,
        public string $symbol,
        public Address $issuerAddress,
        public int $precision,
        public Amount $totalSupply,
        public int $startsAtMilliseconds,
        public int $endsAtMilliseconds,
        array $rawData,
    ) {
        $this->rawData = $rawData;
    }

    /**
     * Creates TRC-10 metadata from a native node response without float casts.
     *
     * @param array<string, mixed> $data Native asset object.
     */
    public static function fromNodeData(array $data): self
    {
        $id = DataDecoder::string($data['id'] ?? null, 'id');
        if (preg_match('/^[1-9][0-9]*$/D', $id) !== 1) {
            throw new ResponseDecodingException('A TRC-10 asset ID must be a positive decimal integer.');
        }

        $precision = DataDecoder::integer($data['precision'] ?? 0, 'precision');
        if ($precision > 6) {
            throw new ResponseDecodingException('A TRC-10 precision cannot exceed six decimals.');
        }

        return new self(
            $id,
            DataDecoder::string($data['name'] ?? '', 'name'),
            DataDecoder::string($data['abbr'] ?? '', 'abbr'),
            Address::fromString(DataDecoder::string($data['owner_address'] ?? null, 'owner_address')),
            $precision,
            Amount::fromAtomic(
                DataDecoder::unsignedDecimal($data['total_supply'] ?? null, 'total_supply'),
                $precision,
            ),
            DataDecoder::integer($data['start_time'] ?? null, 'start_time'),
            DataDecoder::integer($data['end_time'] ?? null, 'end_time'),
            $data,
        );
    }

    /**
     * Creates an exact amount in this asset's smallest unit.
     */
    public function amountFromAtomic(int|string $value): Amount
    {
        return Amount::fromAtomic($value, $this->precision);
    }

    /**
     * Creates an exact human-readable amount using this asset's precision.
     */
    public function amountFromDecimal(int|string $value): Amount
    {
        return Amount::fromDecimal($value, $this->precision);
    }

    /**
     * Returns the untouched asset response.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless asset response.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
