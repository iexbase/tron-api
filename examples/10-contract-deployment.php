<?php

declare(strict_types=1);

use IEXBase\TronAPI\Contract\Abi;
use IEXBase\TronAPI\Contract\DeploymentRequest;
use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\Amount;
use IEXBase\TronAPI\Value\ByteString;

require_once __DIR__ . '/ExampleEnvironment.php';

$signer = ExampleEnvironment::signer();
$request = new DeploymentRequest(
    $signer->address(),
    ExampleEnvironment::value('TRON_CONTRACT_NAME', 'ExampleContract'),
    Abi::fromJson(ExampleEnvironment::file('TRON_ABI_FILE')),
    ByteString::fromHex(trim(ExampleEnvironment::file('TRON_BYTECODE_FILE'))),
    ExampleEnvironment::jsonArray('TRON_CONSTRUCTOR_ARGUMENTS'),
    Amount::fromDecimal(ExampleEnvironment::value('TRON_FEE_LIMIT', '1000')),
    originEnergyLimit: (int) ExampleEnvironment::value('TRON_ORIGIN_ENERGY_LIMIT', '10000000'),
);
$deployment = ExampleEnvironment::tron()->contracts()->deploy($request);

ExampleEnvironment::output([
    'transaction_id' => $deployment->transaction->id(),
    'predicted_contract_address' => $deployment->contractAddress,
]);

ExampleEnvironment::signAndMaybeBroadcast($deployment->transaction);
