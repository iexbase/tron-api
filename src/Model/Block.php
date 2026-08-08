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
use JsonSerializable;

/**
 * Represents one latest or confirmed block with its native transactions.
 */
final readonly class Block implements JsonSerializable
{
    /** @var list<array<string, mixed>> */
    private array $transactions;

    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores block identity, chain position, time, and native transaction data.
     *
     * @param list<array<string, mixed>> $transactions Native transaction objects.
     * @param array<string, mixed>       $rawData Complete block response.
     */
    private function __construct(
        public string $id,
        public int $number,
        public int $timestampMilliseconds,
        public bool $confirmed,
        array $transactions,
        array $rawData,
    ) {
        $this->transactions = $transactions;
        $this->rawData = $rawData;
    }

    /**
     * Creates a block from a native node response and its selected consistency level.
     *
     * @param array<string, mixed> $data Decoded block object.
     */
    public static function fromNodeData(array $data, bool $confirmed): self
    {
        $header = DataDecoder::object($data['block_header'] ?? null, 'block_header');
        $headerData = DataDecoder::object($header['raw_data'] ?? null, 'block_header.raw_data');
        $blockId = Hex::canonicalize(DataDecoder::string($data['blockID'] ?? null, 'blockID'), 32);
        $transactions = DataDecoder::objectList($data['transactions'] ?? [], 'transactions');

        return new self(
            $blockId,
            DataDecoder::integer($headerData['number'] ?? 0, 'block_header.raw_data.number'),
            DataDecoder::integer($headerData['timestamp'] ?? null, 'block_header.raw_data.timestamp'),
            $confirmed,
            $transactions,
            $data,
        );
    }

    /**
     * Returns native transaction objects in their exact block order.
     *
     * @return list<array<string, mixed>>
     */
    public function transactions(): array
    {
        return $this->transactions;
    }

    /**
     * Returns the untouched block response for advanced protocol inspection.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless block object.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
