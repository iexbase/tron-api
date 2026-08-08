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

use IEXBase\TronAPI\Contract\ContractFailure;

/**
 * Reports a TVM revert while preserving its decoded Solidity failure payload.
 */
final class ContractExecutionException extends TronApiException
{
    /**
     * Creates an execution exception from a decoded contract failure.
     */
    public function __construct(public readonly ContractFailure $failure, ?\Throwable $previous = null)
    {
        parent::__construct($failure->message, 0, $previous);
    }
}
