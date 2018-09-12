<?php
include_once '../vendor/autoload.php';

$tron = new \IEXBase\TronAPI\Tron('address');

$detail = $tron->getTransaction('TxId');
var_dump($detail);