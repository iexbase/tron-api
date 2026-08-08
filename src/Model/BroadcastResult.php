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
 * Represents node acceptance of an exact signed transaction payload.
 *
 * Acceptance only means the transaction entered node processing; callers must
 * later inspect a confirmed receipt to determine smart-contract execution success.
 */
final readonly class BroadcastResult implements JsonSerializable
{
    /** @var array<string, mixed> */
    private array $rawData;

    /**
     * Stores the accepted transaction identifier and native response.
     *
     * @param array<string, mixed> $rawData Complete broadcast response.
     */
    private function __construct(public string $transactionId, array $rawData)
    {
        $this->rawData = $rawData;
    }

    /**
     * Creates a result after ApiResponse has already rejected `result=false`.
     *
     * @param array<string, mixed> $data Accepted native broadcast response.
     */
    public static function fromAcceptedNodeData(array $data): self
    {
        return new self(
            Hex::canonicalize(DataDecoder::string($data['txid'] ?? null, 'txid'), 32),
            $data,
        );
    }

    /**
     * Returns the untouched node response.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->rawData;
    }
}
