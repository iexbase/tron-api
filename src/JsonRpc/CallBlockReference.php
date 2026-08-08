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

namespace IEXBase\TronAPI\JsonRpc;

use JsonSerializable;

/**
 * Selects a block whose existence java-tron verifies before executing eth_call.
 *
 * java-tron still executes against the latest state. This value object exposes
 * the documented block object without implying historical-state execution.
 */
final readonly class CallBlockReference implements JsonSerializable
{
    /**
     * Stores exactly one already validated block selector.
     */
    private function __construct(
        private ?Quantity $blockNumber,
        private ?string $blockHash,
    ) {
    }

    /**
     * Selects a block by its canonical JSON-RPC quantity.
     */
    public static function forNumber(Quantity $blockNumber): self
    {
        return new self($blockNumber, null);
    }

    /**
     * Selects a block by its exact 32-byte hash.
     */
    public static function forHash(string $blockHash): self
    {
        return new self(null, JsonRpcParameter::hash32($blockHash));
    }

    /**
     * Returns the documented eth_call block-reference object.
     *
     * @return array{blockNumber: string}|array{blockHash: string}
     */
    public function toArray(): array
    {
        return $this->blockNumber === null
            ? ['blockHash' => $this->blockHash ?? throw new \LogicException('A call block hash is missing.')]
            : ['blockNumber' => $this->blockNumber->hex()];
    }

    /**
     * Serializes the block reference as its JSON-RPC object.
     *
     * @return array{blockNumber: string}|array{blockHash: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
