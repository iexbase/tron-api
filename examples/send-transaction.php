<?php
include_once '../vendor/autoload.php';

$tron = new \IEXBase\TronAPI\Tron();
$tron->setAddress('address');
$tron->setPrivateKey('privateKey');

try {
    $transfer = $tron->sendTransaction('FromAddress', 'ToAddress', 1);
} catch (\IEXBase\TronAPI\Exception\TronException $e) {
    die($e->getMessage());
}

var_dump($transfer);