<?php
include_once '../vendor/autoload.php';

use IEXBase\TronAPI\Provider\HttpProvider;
use IEXBase\TronAPI\Tron;

$fullNode = new HttpProvider('https://api.trongrid.io');
$solidityNode = new HttpProvider('https://api.trongrid.io');
$privateKey = 'private_key';

//Example 1
$tron = new Tron($fullNode, $solidityNode, $privateKey);

//Example 2
$tron->setFullNode($fullNode);
$tron->setSolidityNode($solidityNode);
$tron->setPrivateKey($privateKey);