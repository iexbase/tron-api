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
 * Defines indexed smart-contract event access independently of any vendor.
 */
interface EventProviderInterface
{
    /**
     * Returns every indexed event emitted by one outer transaction.
     */
    public function transactionEvents(string $transactionId, PageRequest $page): IndexerPage;

    /**
     * Returns contract events matching the requested filters.
     */
    public function contractEvents(Address $contractAddress, EventSearch $search): IndexerPage;

    /**
     * Returns indexed events emitted in one block.
     */
    public function blockEvents(int $blockNumber, EventSearch $search): IndexerPage;

    /**
     * Returns events emitted in the indexer's latest available block.
     */
    public function latestBlockEvents(EventSearch $search): IndexerPage;
}
