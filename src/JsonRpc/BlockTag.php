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

namespace IEXBase\TronAPI\JsonRpc;

/**
 * Enumerates symbolic block references accepted by TRON JSON-RPC methods.
 */
enum BlockTag: string
{
    case Earliest = 'earliest';
    case Latest = 'latest';
    case Pending = 'pending';
}
