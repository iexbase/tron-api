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

namespace IEXBase\TronAPI\Contract;

use IEXBase\TronAPI\Value\ByteString;

/**
 * Represents a decoded Solidity Error, Panic, custom error, or unknown revert.
 */
final readonly class ContractFailure
{
    /**
     * Stores the selector, resolved signature, decoded arguments, and raw bytes.
     */
    public function __construct(
        public string $kind,
        public string $selector,
        public ?string $signature,
        public string $message,
        public DecodedValues $arguments,
        public ByteString $rawData,
    ) {
    }
}
