<?php

declare(strict_types=1);

use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\Address;
use IEXBase\TronAPI\Value\Amount;

require_once __DIR__ . '/ExampleEnvironment.php';

$signer = LocalPrivateKeySigner::generate();
$address = $signer->address();
$restoredAddress = Address::fromString($address->toBase58());
$amount = Amount::fromDecimal(ExampleEnvironment::value('TRON_AMOUNT', '1.250001'));

ExampleEnvironment::output([
    'account' => [
        'private_key' => $signer->exportPrivateKey(),
        'public_key' => $signer->publicKeyHex(),
        'base58_address' => $address->toBase58(),
        'tron_hex_address' => $address->toHex(),
        'evm_hex_address' => $address->toEvmHex(),
        'valid' => Address::isValid($address->toBase58()),
        'round_trip_matches' => $restoredAddress->equals($address),
    ],
    'amount' => [
        'sun' => $amount->atomicValue(),
        'trx' => $amount->decimalValue(false),
    ],
]);
