<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Indexer\PageRequest;
use IEXBase\TronAPI\Indexer\TransactionSearch;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$address = ExampleEnvironment::address('TRON_ADDRESS');
$provider = $tron->accountHistory()
    ?? throw new \RuntimeException('No account-history provider is configured.');
$search = new TransactionSearch(
    new PageRequest(
        (int) ExampleEnvironment::value('TRON_PAGE_LIMIT', '20'),
        ExampleEnvironment::optional('TRON_PAGE_CURSOR'),
    ),
    confirmed: true,
);

ExampleEnvironment::output([
    'account' => $provider->account($address),
    'transactions' => $provider->transactions($address, $search),
    'trc20_transactions' => $provider->trc20Transactions($address, $search),
]);
