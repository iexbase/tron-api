<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\NativeAssetId;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$sellAsset = NativeAssetId::fromString(ExampleEnvironment::value('TRON_SELL_ASSET', '_'));
$buyAsset = NativeAssetId::fromString(ExampleEnvironment::required('TRON_BUY_ASSET'));

ExampleEnvironment::output([
    'exchanges' => $tron->exchanges()->list(),
    'market_pairs' => $tron->market()->pairs(),
    'orders' => $tron->market()->orders($sellAsset, $buyAsset),
    'prices' => $tron->market()->prices($sellAsset, $buyAsset),
]);
