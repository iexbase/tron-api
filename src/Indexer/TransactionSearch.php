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

namespace IEXBase\TronAPI\Indexer;

use IEXBase\TronAPI\Enum\SortOrder;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Value\Address;

/**
 * Defines vendor-neutral filters for indexed account transaction searches.
 */
final readonly class TransactionSearch
{
    /**
     * Validates confirmation, direction, contract, timestamp, and pagination filters.
     */
    public function __construct(
        public PageRequest $page = new PageRequest(),
        public ?bool $confirmed = true,
        public bool $outgoingOnly = false,
        public bool $incomingOnly = false,
        public ?Address $contractAddress = null,
        public TimestampRange $timestamps = new TimestampRange(),
        public SortOrder $sortOrder = SortOrder::Descending,
    ) {
        if ($outgoingOnly && $incomingOnly) {
            throw new ValidationException('A transaction search cannot be both outgoing-only and incoming-only.');
        }
    }
}
