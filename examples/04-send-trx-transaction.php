<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

require_once __DIR__ . '/ExampleEnvironment.php';

$signer = ExampleEnvironment::signer();
$tron = ExampleEnvironment::tron();
$recipient = ExampleEnvironment::address('TRON_RECIPIENT');
$amount = Amount::fromDecimal(ExampleEnvironment::value('TRON_AMOUNT', '1'));
$memo = Memo::fromText(ExampleEnvironment::value('TRON_MEMO', 'TronAPI 6.0 example'));
$transaction = $tron->transfers()->createTrxTransfer(
    $signer->address(),
    $recipient,
    $amount,
    $memo,
);
$bandwidthPrice = $tron->network()->bandwidthUnitPrice();
$intent = $transaction->approvedIntent();

ExampleEnvironment::output([
    'sender' => $signer->address(),
    'recipient' => $recipient,
    'amount' => $amount,
    'memo' => $memo,
    'transaction_id' => $transaction->id(),
    'approved_contract' => $intent->contractType,
    'permission_id' => $intent->permissionId,
    'unsigned' => !$transaction->isSigned(),
    'estimated_bandwidth' => $tron->transactions()->estimateBandwidth($transaction),
    'maximum_bandwidth_burn' => $tron->transactions()
        ->maximumBandwidthBurn($transaction, $bandwidthPrice)
        ->decimalValue(false),
]);

ExampleEnvironment::signAndMaybeBroadcast($transaction);
