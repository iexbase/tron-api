<?php
include_once '../vendor/autoload.php';

$tron = new \IEXBase\TronAPI\Tron('address');

$balance = $tron->getBalance(); //or $tron->getBalance('address');

var_dump($balance);