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

use Throwable;

/**
 * Reports a connection, TLS, timeout, response-limit, or lower-level transport failure.
 */
final class TransportException extends TronApiException
{
    /**
     * Creates a transport failure and declares whether repeating the request can help.
     *
     * @param string         $message Human-readable transport failure description.
     * @param int            $code Application-specific exception code.
     * @param Throwable|null $previous Original lower-level failure, when available.
     * @param bool           $retryable Whether repeating the same HTTP request may succeed.
     */
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly bool $retryable = true,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
