<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Indexer\PageRequest;
use IEXBase\TronAPI\Indexer\TransactionSearch;
use IEXBase\TronAPI\Value\Address;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$addressValue = ExampleEnvironment::optional('TRON_ADDRESS');
$transactionId = ExampleEnvironment::optional('TRON_TRANSACTION_ID');

if ($addressValue === null && $transactionId === null) {
    throw new \RuntimeException('Set TRON_ADDRESS, TRON_TRANSACTION_ID, or both.');
}

$provider = $tron->accountHistory();
$limit = (int) ExampleEnvironment::value('TRON_PAGE_LIMIT', '20');
$result = [];

if ($addressValue !== null) {
    $provider ??= throw new \RuntimeException('No account-history provider is configured.');
    $address = Address::fromString($addressValue);
    $result['account'] = $provider->account($address);
    $result['transactions'] = $provider->transactions($address, new TransactionSearch(
        new PageRequest($limit, ExampleEnvironment::optional('TRON_TRANSACTION_CURSOR')),
        confirmed: true,
    ));
    $result['trc20_transactions'] = $provider->trc20Transactions($address, new TransactionSearch(
        new PageRequest($limit, ExampleEnvironment::optional('TRON_TRC20_CURSOR')),
        confirmed: true,
    ));
    $result['internal_transactions'] = $provider->internalTransactions(
        $address,
        new PageRequest($limit, ExampleEnvironment::optional('TRON_INTERNAL_CURSOR')),
    );
}

if ($transactionId !== null) {
    $result['transaction_details'] = [
        'transaction' => $tron->transactions()->find($transactionId),
        'receipt' => $tron->transactions()->receipt($transactionId),
        'internal_transactions' => $provider?->transactionInternalTransactions(
            $transactionId,
            new PageRequest($limit),
        ),
    ];
}

ExampleEnvironment::output($result);
