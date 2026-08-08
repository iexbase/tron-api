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
use JsonSerializable;

/**
 * Represents an exact Energy estimate returned by a supporting node.
 */
final readonly class EnergyEstimate implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores the Energy requirement as decimal text to prevent numeric overflow.
     *
     * @param array<string, mixed> $rawData Complete estimate response.
     */
    private function __construct(public string $energyRequired, array $rawData)
    {
        $this->rawData = $rawData;
    }

    /**
     * Creates an estimate after the node result envelope has been accepted.
     *
     * @param array<string, mixed> $data Native estimate response.
     */
    public static function fromNodeData(array $data): self
    {
        return new self(
            DataDecoder::unsignedDecimal($data['energy_required'] ?? null, 'energy_required'),
            $data,
        );
    }

    /**
     * Returns the untouched estimate response.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
