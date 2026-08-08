<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$address = ExampleEnvironment::address('TRON_ADDRESS');
$account = $tron->accounts()->get($address);

if ($account === null) {
    ExampleEnvironment::output([
        'address' => $address,
        'activated' => false,
    ]);

    return;
}

ExampleEnvironment::output([
    'address' => $account->address,
    'activated' => true,
    'trx_balance' => [
        'sun' => $account->balance->atomicValue(),
        'trx' => $account->balance->decimalValue(false),
    ],
    'trc10_balances' => $account->assetBalances(),
    'resources' => $tron->accounts()->resources($address),
    'bandwidth' => $tron->accounts()->bandwidth($address),
    'latest_confirmed_block' => $tron->blocks()->latest(),
    'node' => $tron->network()->nodeInfo(),
    'chain_parameters' => $tron->network()->chainParameters(),
    'energy_prices' => $tron->network()->energyPrices(),
    'bandwidth_prices' => $tron->network()->bandwidthPrices(),
    'memo_fees' => $tron->network()->memoFees(),
]);
