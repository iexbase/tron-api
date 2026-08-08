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

namespace IEXBase\TronAPI\Contract;

use IEXBase\TronAPI\Value\ByteString;
use JsonSerializable;

/**
 * Represents decoded output and exact resource usage from a simulated call.
 */
final readonly class ContractCallResult implements JsonSerializable
{
    /** @var list<ByteString> */
    private array $rawResults;

    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores decoded outputs, all raw result buffers, and Energy accounting.
     *
     * @param list<ByteString>     $rawResults Native constant_result entries.
     * @param array<string, mixed> $rawData Complete simulation response.
     */
    public function __construct(
        public DecodedValues $outputs,
        array $rawResults,
        public string $energyUsed,
        public string $energyPenalty,
        array $rawData,
    ) {
        $this->rawResults = $rawResults;
        $this->rawData = $rawData;
    }

    /**
     * Returns every unmodified result buffer emitted by the node.
     *
     * @return list<ByteString>
     */
    public function rawResults(): array
    {
        return $this->rawResults;
    }

    /**
     * Returns the complete node simulation response.
     *
     * @return array<string, mixed>
     */
    public function rawData(): array
    {
        return $this->rawData;
    }

    /**
     * Serializes the original lossless simulation response.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
