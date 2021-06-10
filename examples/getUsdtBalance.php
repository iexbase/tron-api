<?php
include_once '../vendor/autoload.php';

$fullNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
$solidityNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
$eventServer = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');

try {
    $tron = new \IEXBase\TronAPI\Tron($fullNode, $solidityNode, $eventServer);
} catch (\IEXBase\TronAPI\Exception\TronException $e) {
    exit($e->getMessage());
}


$tron->setAddress('address');
$contract = $tron->contract('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
$usdtBalance = $contract->balanceOf($this->payment->tronData->address_base_58);
echo $usdtBalance;
