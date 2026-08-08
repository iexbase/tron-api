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
 * Reports a validation or operation error returned by a TRON node.
 */
final class NodeException extends TronApiException
{
    /**
     * Creates a node exception and retains the protocol error code when known.
     *
     * @param string      $message Decoded node error message.
     * @param string|null $nodeCode Protocol error code such as SIGERROR.
     */
    public function __construct(
        string $message,
        public readonly ?string $nodeCode = null,
    ) {
        parent::__construct($message);
    }
}
