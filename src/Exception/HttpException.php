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
 * Reports an HTTP response whose status code is outside the successful range.
 */
final class HttpException extends TronApiException
{
    /**
     * Creates an HTTP exception while retaining the status and response body.
     *
     * @param int    $statusCode HTTP response status code.
     * @param string $responseBody Unmodified response body for diagnostics.
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $responseBody,
    ) {
        parent::__construct(sprintf('TRON endpoint returned HTTP %d.', $statusCode), $statusCode);
    }
}
