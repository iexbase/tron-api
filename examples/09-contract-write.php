<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$signer = ExampleEnvironment::signer();
$token = $tron->contracts()->trc20(
    ExampleEnvironment::address('TRON_CONTRACT'),
    $signer->address(),
);
$amount = Amount::fromDecimal(
    ExampleEnvironment::value('TRON_TOKEN_AMOUNT', '1'),
    $token->decimals(),
);
$transaction = $token->transfer(
    ExampleEnvironment::address('TRON_RECIPIENT'),
    $amount,
    Amount::fromDecimal(ExampleEnvironment::value('TRON_FEE_LIMIT', '100')),
    Memo::fromText(ExampleEnvironment::value('TRON_MEMO', 'TRC-20 transfer')),
);

ExampleEnvironment::signAndMaybeBroadcast($transaction);
