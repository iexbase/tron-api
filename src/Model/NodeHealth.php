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

use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Summarizes latest/confirmed node progress without relying on an explorer.
 */
final readonly class NodeHealth
{
    /**
     * Stores both heights and calculates irreversible-state lag in blocks.
     */
    public function __construct(
        public int $latestBlockNumber,
        public int $confirmedBlockNumber,
        public int $maximumHealthyLag = 64,
    ) {
        if ($latestBlockNumber < 0 || $confirmedBlockNumber < 0 || $maximumHealthyLag < 0) {
            throw new ValidationException('Node health heights and the maximum lag must be non-negative.');
        }
    }

    /**
     * Returns the non-negative distance between latest and confirmed state.
     */
    public function confirmationLag(): int
    {
        return max(0, $this->latestBlockNumber - $this->confirmedBlockNumber);
    }

    /**
     * Returns whether both roles are ordered and lag is within the chosen limit.
     */
    public function isHealthy(): bool
    {
        return $this->confirmedBlockNumber <= $this->latestBlockNumber
            && $this->confirmationLag() <= $this->maximumHealthyLag;
    }
}
