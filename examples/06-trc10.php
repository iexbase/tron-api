<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\NativeAssetId;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$signer = ExampleEnvironment::signer();
$asset = $tron->assets()->byId(ExampleEnvironment::required('TRON_TRC10_ID'))
    ?? throw new \RuntimeException('The requested TRC-10 asset does not exist.');
$account = $tron->accounts()->get($signer->address());
$balance = $account?->assetBalance(NativeAssetId::fromString($asset->id));

ExampleEnvironment::output([
    'asset' => $asset,
    'owner_address' => $signer->address(),
    'balance' => $balance === null ? null : [
        'atomic' => $balance->atomicValue(),
        'decimal' => $balance->amount($asset->precision)->decimalValue(false),
    ],
]);

$transaction = $tron->assets()->createTransfer(
    $signer->address(),
    ExampleEnvironment::address('TRON_RECIPIENT'),
    $asset,
    $asset->amountFromDecimal(ExampleEnvironment::value('TRON_TOKEN_AMOUNT', '1')),
);

ExampleEnvironment::signAndMaybeBroadcast($transaction);
