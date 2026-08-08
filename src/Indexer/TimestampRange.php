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

use IEXBase\TronAPI\Exception\ValidationException;

/**
 * Represents optional inclusive Unix-millisecond boundaries for indexer searches.
 */
final readonly class TimestampRange
{
    /**
     * Ensures both boundaries are non-negative and chronologically ordered.
     */
    public function __construct(
        public ?int $minimumMilliseconds = null,
        public ?int $maximumMilliseconds = null,
    ) {
        if (($minimumMilliseconds !== null && $minimumMilliseconds < 0)
            || ($maximumMilliseconds !== null && $maximumMilliseconds < 0)
        ) {
            throw new ValidationException('Indexed timestamps cannot be negative.');
        }

        if ($minimumMilliseconds !== null
            && $maximumMilliseconds !== null
            && $minimumMilliseconds > $maximumMilliseconds
        ) {
            throw new ValidationException('The minimum indexed timestamp cannot exceed the maximum timestamp.');
        }
    }
}
