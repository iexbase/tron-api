<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\JsonRpc\BlockTag;

require_once __DIR__ . '/ExampleEnvironment.php';

$rpc = ExampleEnvironment::tron()->jsonRpc();
$address = ExampleEnvironment::address('TRON_ADDRESS');
$blockNumber = $rpc->blockNumber();

ExampleEnvironment::output([
    'chain_id' => $rpc->chainId(),
    'block_number' => $blockNumber,
    'balance' => $rpc->balance($address),
    'code' => $rpc->code($address, BlockTag::Latest),
    'block' => $rpc->blockByNumber($blockNumber),
]);
