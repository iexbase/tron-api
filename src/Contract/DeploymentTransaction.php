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

use IEXBase\TronAPI\Crypto\ContractAddress;
use IEXBase\TronAPI\Exception\ContractException;
use IEXBase\TronAPI\Transaction\Transaction;
use IEXBase\TronAPI\Value\Address;

/**
 * Couples a verified unsigned deployment transaction with its derived address.
 */
final readonly class DeploymentTransaction
{
    /**
     * Derives the address locally and verifies the node's top-level prediction.
     */
    public function __construct(
        public Transaction $transaction,
        Address $ownerAddress,
    ) {
        $derived = ContractAddress::fromTransaction($ownerAddress, $transaction->id());
        $reportedValue = $transaction->toArray()['contract_address'] ?? null;
        if (!is_string($reportedValue)) {
            throw new ContractException('The deployment response is missing its predicted contract address.');
        }

        $reported = Address::fromString($reportedValue);
        if (!$reported->equals($derived)) {
            throw new ContractException('The node-reported deployment address does not match local derivation.');
        }

        $this->contractAddress = $derived;
    }

    /**
     * Contains the address that will exist only after successful confirmed execution.
     */
    public Address $contractAddress;
}
