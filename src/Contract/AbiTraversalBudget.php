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

use IEXBase\TronAPI\Exception\ContractException;

/**
 * Bounds the total recursive ABI values visited during one encode or decode.
 *
 * A shared budget prevents repeated dynamic offsets from expanding a compact
 * untrusted payload into unbounded recursive work.
 */
final class AbiTraversalBudget
{
    private const DEFAULT_VALUE_LIMIT = 200_000;

    /**
     * Creates an operation budget with a production-safe default limit.
     */
    public function __construct(private int $remainingValues = self::DEFAULT_VALUE_LIMIT)
    {
        if ($remainingValues < 1) {
            throw new ContractException('An ABI traversal budget must be positive.');
        }
    }

    /**
     * Reserves work for a sequence and rejects aggregate recursive expansion.
     */
    public function consume(int $valueCount): void
    {
        if ($valueCount < 0 || $valueCount > $this->remainingValues) {
            throw new ContractException('ABI traversal exceeds the configured aggregate value limit.');
        }

        $this->remainingValues -= $valueCount;
    }
}
