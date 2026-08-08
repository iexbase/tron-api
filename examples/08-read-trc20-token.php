<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\Address;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$account = ExampleEnvironment::address('TRON_ADDRESS');
$token = $tron->contracts()->trc20(
    ExampleEnvironment::address('TRON_CONTRACT'),
    $account,
);
$spenderValue = ExampleEnvironment::optional('TRON_SPENDER');
$spender = $spenderValue === null ? null : Address::fromString($spenderValue);

ExampleEnvironment::output([
    'contract' => $token->contractAddress,
    'account' => $account,
    'name' => $token->name(),
    'symbol' => $token->symbol(),
    'decimals' => $token->decimals(),
    'total_supply' => $token->totalSupply(),
    'balance' => $token->balanceOf($account),
    'allowance' => $spender === null ? null : $token->allowance($account, $spender),
]);
