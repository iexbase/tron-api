<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;

require_once __DIR__ . '/ExampleEnvironment.php';

$rpc = ExampleEnvironment::tron()->jsonRpc();
$address = ExampleEnvironment::address('TRON_ADDRESS');
$blockNumber = $rpc->blockNumber();

ExampleEnvironment::output([
    'chain_id' => $rpc->chainId(),
    'node_accounts' => $rpc->accounts(),
    'block_number' => $blockNumber,
    'balance' => $rpc->balance($address),
    'code' => $rpc->code($address),
    'block' => $rpc->blockByNumber($blockNumber),
]);
