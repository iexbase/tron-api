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

namespace IEXBase\TronAPI\Crypto;

use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Value\Address;
use kornrunner\Keccak;

/**
 * Derives the deterministic address created by a contract deployment transaction.
 */
final class ContractAddress
{
    /**
     * Returns `0x41 || last20(Keccak-256(txID || ownerAddress))` as an Address.
     */
    public static function fromTransaction(Address $ownerAddress, string $transactionId): Address
    {
        $payload = Hex::toBytes($transactionId, 32) . $ownerAddress->bytes();
        $hash = Keccak::hash($payload, 256);

        return Address::fromHex(Address::PREFIX_HEX . substr($hash, -40));
    }
}
