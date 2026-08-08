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

namespace IEXBase\TronAPI\Exception;

/**
 * Reports an indexer or node rate limit and exposes the advised retry delay.
 */
final class RateLimitException extends TronApiException
{
    /**
     * Creates a rate-limit exception with an optional Retry-After duration.
     *
     * @param string   $message Human-readable rate-limit description.
     * @param int|null $retryAfterSeconds Server-advised delay, when supplied.
     */
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
