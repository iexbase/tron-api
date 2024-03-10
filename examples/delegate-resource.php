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


$tron->setAddress('public_key');
$tron->setPrivateKey('private_key');

try {
    $transfer = $tron->delegateResource( null, 'BANDWIDTH', 'receiver_public_key', 1, false, 0);
} catch (\IEXBase\TronAPI\Exception\TronException $e) {
    die($e->getMessage());
}

var_dump($transfer);