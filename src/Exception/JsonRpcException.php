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
 * Reports a valid JSON-RPC error response while preserving its structured data.
 */
final class JsonRpcException extends TronApiException
{
    /**
     * Creates an exception from the standard JSON-RPC error object.
     */
    public function __construct(
        public readonly int $rpcCode,
        string $message,
        public readonly mixed $rpcData = null,
    ) {
        parent::__construct($message, $rpcCode);
    }
}
