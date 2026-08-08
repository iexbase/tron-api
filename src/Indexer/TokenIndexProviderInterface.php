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
 * Defines optional indexed token discovery independently of any provider.
 */
interface TokenIndexProviderInterface
{
    /**
     * Returns indexed TRC-20 balances for one account.
     */
    public function trc20Balances(Address $address, PageRequest $page): IndexerPage;

    /**
     * Returns indexed TRC-20 metadata records.
     */
    public function trc20Information(PageRequest $page): IndexerPage;

    /**
     * Returns indexed tokens associated with one contract.
     */
    public function contractTokens(Address $contractAddress, PageRequest $page): IndexerPage;
}
