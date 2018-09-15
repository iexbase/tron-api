<?php
include_once '../vendor/autoload.php';

use IEXBase\TronAPI\Providers\HttpProvider;
use IEXBase\TronAPI\Tron;

$fullNode = new HttpProvider('https://api.trongrid.io:8090');
$solidityNode = new HttpProvider('https://api.trongrid.io:8091');
$privateKey = 'private_key';

//Example 1
$tron = new Tron($fullNode, $solidityNode, $privateKey);

//Example 2
$tron->setFullNode($fullNode);
$tron->setSolidityNode($solidityNode);
$tron->setPrivateKey($privateKey);