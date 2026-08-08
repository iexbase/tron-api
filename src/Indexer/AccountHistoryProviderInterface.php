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

namespace IEXBase\TronAPI\Indexer;

use IEXBase\TronAPI\Value\Address;

/**
 * Defines indexed account and transaction history independently of TronGrid.
 */
interface AccountHistoryProviderInterface
{
    /**
     * Returns the indexed account record, or null when the provider has none.
     *
     * @return array<string, mixed>|null
     */
    public function account(Address $address): ?array;

    /**
     * Returns native account transactions matching explicit filters.
     */
    public function transactions(Address $address, TransactionSearch $search): IndexerPage;

    /**
     * Returns TRC-20 account transactions matching explicit filters.
     */
    public function trc20Transactions(Address $address, TransactionSearch $search): IndexerPage;

    /**
     * Returns indexed internal transactions initiated by or received by an account.
     */
    public function internalTransactions(Address $address, PageRequest $page): IndexerPage;

    /**
     * Returns indexed internal transactions belonging to one outer transaction.
     */
    public function transactionInternalTransactions(string $transactionId, PageRequest $page): IndexerPage;
}
