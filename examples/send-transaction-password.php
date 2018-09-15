<?php
include_once '../vendor/autoload.php';

$tron = new \IEXBase\TronAPI\Tron();

$transfer = $tron->sendTransactionByPassword('To Address',1,'Password');

echo '<pre>';
    print_r($transfer);
echo '</pre>';
