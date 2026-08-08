<?php

declare(strict_types=1);

use IEXBase\TronAPI\Enum\ResourceType;
use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\Amount;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$signer = ExampleEnvironment::signer();
$amount = Amount::fromDecimal(ExampleEnvironment::value('TRON_STAKE_AMOUNT', '1'));

ExampleEnvironment::output([
    'available_unstake_slots' => $tron->stake()->availableUnstakeCount($signer->address()),
    'withdrawable' => $tron->stake()->withdrawableAmount($signer->address()),
    'delegatable_energy' => $tron->stake()->delegatableAmount($signer->address(), ResourceType::Energy),
]);

$transaction = $tron->stake()->stake($signer->address(), $amount, ResourceType::Energy);
ExampleEnvironment::signAndMaybeBroadcast($transaction);
