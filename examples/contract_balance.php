<?php
$fullNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
$solidityNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
$eventServer = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');

try {
    $tron = new \IEXBase\TronAPI\Tron($fullNode, $solidityNode, $eventServer);
} catch (\IEXBase\TronAPI\Exception\TronException $e) {
    exit($e->getMessage());
}


$balance=$tron->getTransactionBuilder()->contractbalance($tron->getAddress);
foreach($balance as $key =>$item)
{
	echo $item["name"]. " (".$item["symbol"].") => " . $item["balance"] . "\n";
}

