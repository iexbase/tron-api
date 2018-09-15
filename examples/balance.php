<?php
include_once '../vendor/autoload.php';

$tron = new \IEXBase\TronAPI\Tron();
$tron->setAddress('address');

$balance = $tron->getBalance();

echo $tron->fromTron($balance);
