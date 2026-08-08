<?php

declare(strict_types=1);

use IEXBase\TronAPI\Api\DataDecoder;
use IEXBase\TronAPI\Examples\ExampleEnvironment;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$transactionId = ExampleEnvironment::required('TRON_TRANSACTION_ID');
$contract = $tron->contracts()->get(ExampleEnvironment::address('TRON_CONTRACT'))
    ?? throw new \RuntimeException('The requested contract does not exist.');
$transaction = $tron->transactions()->find($transactionId)
    ?? throw new \RuntimeException('The requested confirmed transaction does not exist.');
$receipt = $tron->transactions()->receipt($transactionId)
    ?? throw new \RuntimeException('The requested confirmed transaction receipt does not exist.');
$nativeContract = $transaction->singleContract();
if (($nativeContract['type'] ?? null) !== 'TriggerSmartContract') {
    throw new \RuntimeException('The requested transaction is not a smart-contract trigger.');
}
$parameter = DataDecoder::object($nativeContract['parameter'] ?? null, 'contract.parameter');
$value = DataDecoder::object($parameter['value'] ?? null, 'contract.parameter.value');
$functionCall = $tron->contracts()->decodeFunctionCall(
    $contract->abi,
    DataDecoder::string($value['data'] ?? null, 'contract.parameter.value.data'),
);
$receipt->requireSuccessfulExecution();
$events = $tron->contracts()->decodeReceiptEvents($receipt, $contract->abi);

ExampleEnvironment::output([
    'transaction_id' => $receipt->transactionId,
    'execution_result' => $receipt->executionResult,
    'function_call' => $functionCall,
    'events' => $events,
]);
