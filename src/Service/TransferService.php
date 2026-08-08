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

namespace IEXBase\TronAPI\Service;

use IEXBase\TronAPI\Api\Endpoint;
use IEXBase\TronAPI\Exception\ValidationException;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Transaction\TransactionFactory;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

/**
 * Builds verified native TRX transfer transactions using exact sun amounts.
 */
final readonly class TransferService
{
    /**
     * Creates the service over the central verified transaction factory.
     */
    public function __construct(private TransactionFactory $transactions)
    {
    }

    /**
     * Builds a TRX transfer and verifies recipient, amount, memo, and permission.
     */
    public function createTrxTransfer(
        Address $ownerAddress,
        Address $recipientAddress,
        Amount $amount,
        ?Memo $memo = null,
        int $permissionId = 0,
    ): Transaction {
        if ($amount->decimals() !== Amount::TRX_DECIMALS || $amount->isZero()) {
            throw new ValidationException('A TRX transfer requires a positive amount with six decimals.');
        }

        $fields = [
            'to_address' => $recipientAddress,
            'amount' => $amount->atomicInteger(),
        ];

        return $this->transactions->createNativeContract(
            Endpoint::CreateTransaction,
            'TransferContract',
            $ownerAddress,
            $fields,
            $memo,
            $permissionId,
        );
    }
}
