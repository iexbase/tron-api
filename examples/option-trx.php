<?php
include_once '../vendor/autoload.php';

use IEXBase\TronAPI\Tron;

$tron = new Tron();

//option 1
$tron->sendTransaction('from','to',0.1);

//option 2
$tron->send('from','to',0.1);

//option 3
$tron->sendTrx('from','to',0.1);