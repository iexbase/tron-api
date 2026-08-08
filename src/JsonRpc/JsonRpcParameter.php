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

use IEXBase\TronAPI\Encoding\Hex;

/**
 * Encodes shared JSON-RPC block references and fixed-width hashes consistently.
 */
final class JsonRpcParameter
{
    /**
     * Converts a symbolic block tag or exact quantity to its wire scalar.
     */
    public static function block(BlockTag|Quantity $block): string
    {
        return $block instanceof BlockTag ? $block->value : $block->hex();
    }

    /**
     * Returns a canonical 0x-prefixed 32-byte hash.
     */
    public static function hash32(string $value): string
    {
        return '0x' . Hex::canonicalize($value, 32);
    }
}
