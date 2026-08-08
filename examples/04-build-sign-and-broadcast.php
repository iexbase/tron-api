<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

require_once __DIR__ . '/ExampleEnvironment.php';

$signer = ExampleEnvironment::signer();
$tron = ExampleEnvironment::tron();
$transaction = $tron->transfers()->createTrxTransfer(
    $signer->address(),
    ExampleEnvironment::address('TRON_RECIPIENT'),
    Amount::fromDecimal(ExampleEnvironment::value('TRON_AMOUNT', '1')),
    Memo::fromText(ExampleEnvironment::value('TRON_MEMO', 'TronAPI 6.0 example')),
);
$bandwidthPrice = $tron->network()->bandwidthUnitPrice();

ExampleEnvironment::output([
    'transaction_id' => $transaction->id(),
    'approved_contract' => $transaction->approvedIntent()->contractType,
    'unsigned' => !$transaction->isSigned(),
    'estimated_bandwidth' => $tron->transactions()->estimateBandwidth($transaction),
    'maximum_bandwidth_burn' => $tron->transactions()
        ->maximumBandwidthBurn($transaction, $bandwidthPrice)
        ->decimalValue(false),
]);

ExampleEnvironment::signAndMaybeBroadcast($transaction);
