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

namespace IEXBase\TronAPI\Enum;

/**
 * Identifies the TRON data source required by an endpoint.
 *
 * FullNode exposes latest head state and transaction construction. SolidityNode
 * exposes solidified state. Indexer exposes provider-specific indexed data,
 * while JsonRpc exposes the Ethereum-compatible java-tron JSON-RPC interface.
 */
enum NodeRole: string
{
    case FullNode = 'full_node';
    case SolidityNode = 'solidity_node';
    case Indexer = 'indexer';
    case JsonRpc = 'json_rpc';
}
