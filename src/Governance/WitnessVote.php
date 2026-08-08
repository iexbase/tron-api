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

namespace IEXBase\TronAPI\Governance;

use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Address;

/**
 * Represents one positive TRON Power allocation to an SR candidate.
 */
final readonly class WitnessVote
{
    /**
     * Stores the candidate address and exact vote count.
     */
    public function __construct(
        public Address $witnessAddress,
        public int $voteCount,
    ) {
        if ($voteCount <= 0) {
            throw new ValidationException('A witness vote count must be positive.');
        }
    }

    /**
     * Returns fields suitable for a visible=true native endpoint request.
     *
     * @return array{vote_address: string, vote_count: int}
     */
    public function toNodeData(): array
    {
        return [
            'vote_address' => $this->witnessAddress->toBase58(),
            'vote_count' => $this->voteCount,
        ];
    }
}
