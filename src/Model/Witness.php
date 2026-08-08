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
use JsonSerializable;

/**
 * Represents one Super Representative candidate returned by a native node.
 */
final readonly class Witness implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores exact vote and production counters without floating-point conversion.
     *
     * @param array<string, mixed> $rawData Complete witness response.
     */
    private function __construct(
        public Address $address,
        public string $voteCount,
        public string $url,
        public string $totalProduced,
        public string $totalMissed,
        public int $latestBlockNumber,
        public string $latestSlotNumber,
        public bool $active,
        array $rawData,
    ) {
        $this->rawData = $rawData;
    }

    /**
     * Creates a witness from the native list response.
     *
     * @param array<string, mixed> $data Native witness object.
     */
    public static function fromNodeData(array $data): self
    {
        return new self(
            Address::fromString(DataDecoder::string($data['address'] ?? null, 'witness.address')),
            DataDecoder::unsignedDecimal($data['voteCount'] ?? 0, 'witness.voteCount'),
            DataDecoder::string($data['url'] ?? '', 'witness.url'),
            DataDecoder::unsignedDecimal($data['totalProduced'] ?? 0, 'witness.totalProduced'),
            DataDecoder::unsignedDecimal($data['totalMissed'] ?? 0, 'witness.totalMissed'),
            DataDecoder::integer($data['latestBlockNum'] ?? 0, 'witness.latestBlockNum'),
            DataDecoder::unsignedDecimal($data['latestSlotNum'] ?? 0, 'witness.latestSlotNum'),
            isset($data['isJobs']) ? DataDecoder::boolean($data['isJobs'], 'witness.isJobs') : false,
            $data,
        );
    }

    /**
     * Returns the untouched witness response.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless witness response.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
