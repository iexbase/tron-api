<?php
include_once '../vendor/autoload.php';

$fullNode = new \IEXBase\TronAPI\Providers\HttpProvider('http://13.125.210.234:8090');
$solidityNode = new \IEXBase\TronAPI\Providers\HttpProvider('https://api.trongrid.io:8091');

$tron = new \IEXBase\TronAPI\Tron();

if($fullNode->isConnected()) {
    $tron->setFullNode($fullNode);
}

if($solidityNode->isConnected()) {
    $tron->setSolidityNode($solidityNode);
}