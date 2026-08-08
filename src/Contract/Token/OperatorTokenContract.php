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

namespace IEXBase\TronAPI\Contract\Token;

use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;
use IEXBase\TronAPI\Value\Memo;

/**
 * Shares interface detection and operator approvals across TRC-721 and TRC-1155.
 */
abstract class OperatorTokenContract extends TokenContract
{
    /**
     * Returns whether the contract advertises one four-byte interface identifier.
     */
    public function supportsInterface(ByteString $interfaceId): bool
    {
        return $this->booleanOutput($this->read('supportsInterface(bytes4)', [$interfaceId]));
    }

    /**
     * Returns whether an operator may manage every token owned by an account.
     */
    public function isApprovedForAll(Address $owner, Address $operator): bool
    {
        return $this->booleanOutput($this->read('isApprovedForAll(address,address)', [$owner, $operator]));
    }

    /**
     * Builds a verified collection-wide operator approval transaction.
     */
    public function setApprovalForAll(
        Address $operator,
        bool $approved,
        Amount $feeLimit,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        return $this->createTransaction(
            'setApprovalForAll(address,bool)',
            [$operator, $approved],
            $feeLimit,
            $memo,
            $permissionId,
        );
    }
}
