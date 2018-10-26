<?php
include_once '../vendor/autoload.php';

$fullNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io');
$solidityNode = new \IEXBase\TronAPI\Provider\HttpProvider('https://api.trongrid.io:8091');

$tron = new \IEXBase\TronAPI\Tron();

if($fullNode->isConnected()) {
    $tron->setFullNode($fullNode);
}

if($solidityNode->isConnected()) {
    $tron->setSolidityNode($solidityNode);
}