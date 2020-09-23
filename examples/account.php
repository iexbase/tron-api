<?php
include_once '../vendor/autoload.php';

try {
    $tron = new \IEXBase\TronAPI\Tron();

    $generateAddress = $tron->generateAddress(); // or createAddress()
    $isValid = $tron->isAddress($generateAddress->getAddress());


    echo 'Address hex: '. $generateAddress->getAddress();
    echo 'Address base58: '. $generateAddress->getAddress(true);
    echo 'Private key: '. $generateAddress->getPrivateKey();
    echo 'Public key: '. $generateAddress->getPublicKey();
    echo 'Is Validate: '. $isValid;

    echo 'Raw data: '.$generateAddress->getRawData();

} catch (\IEXBase\TronAPI\Exception\TronException $e) {
    echo $e->getMessage();
}



