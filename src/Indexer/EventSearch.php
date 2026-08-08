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

/**
 * Defines provider-neutral event name, confirmation, time, order, and page filters.
 */
final readonly class EventSearch
{
    /**
     * Validates event names and chronological millisecond boundaries.
     */
    public function __construct(
        public PageRequest $page = new PageRequest(),
        public ?string $eventName = null,
        public ?bool $confirmed = true,
        public TimestampRange $timestamps = new TimestampRange(),
        public SortOrder $sortOrder = SortOrder::Descending,
    ) {
        if ($eventName !== null && ($eventName === '' || strlen($eventName) > 256 || preg_match('/[\r\n]/', $eventName) === 1)) {
            throw new ValidationException('An indexed event name must be a bounded single-line value.');
        }
    }
}
