<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\Memo;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$signer = ExampleEnvironment::signer();
$contract = ExampleEnvironment::address('TRON_CONTRACT');
$recipient = ExampleEnvironment::address('TRON_RECIPIENT');
$token = $tron->contracts()->trc20(
    $contract,
    $signer->address(),
);
$amount = Amount::fromDecimal(
    ExampleEnvironment::value('TRON_TOKEN_AMOUNT', '1'),
    $token->decimals(),
);
$feeLimit = Amount::fromDecimal(ExampleEnvironment::value('TRON_FEE_LIMIT', '100'));
$transaction = $token->transfer(
    $recipient,
    $amount,
    $feeLimit,
    Memo::fromText(ExampleEnvironment::value('TRON_MEMO', 'TRC-20 transfer')),
);

ExampleEnvironment::output([
    'contract' => $contract,
    'sender' => $signer->address(),
    'recipient' => $recipient,
    'amount' => $amount,
    'fee_limit' => $feeLimit,
    'transaction_id' => $transaction->id(),
]);

ExampleEnvironment::signAndMaybeBroadcast($transaction);
