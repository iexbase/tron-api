<?php
include_once '../vendor/autoload.php';

$tron = new \IEXBase\TronAPI\Tron('address','privateKey');

try {
    $transfer = $tron->sendTransaction('FromAddress', 'ToAddress', 1);
} catch (\IEXBase\TronAPI\Exceptions\TronException $e) {
    die($e->getMessage());
}

var_dump($transfer);
