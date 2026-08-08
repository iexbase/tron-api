<?php

declare(strict_types=1);

use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Crypto\MessageSigner;
use IEXBase\TronAPI\Examples\ExampleEnvironment;

require_once __DIR__ . '/ExampleEnvironment.php';

$message = ExampleEnvironment::value(
    'TRON_MESSAGE',
    'Sign in to example.test; nonce=replace-with-a-single-use-server-nonce',
);
$configuredPrivateKey = ExampleEnvironment::optional('TRON_PRIVATE_KEY');
$signer = $configuredPrivateKey === null
    ? LocalPrivateKeySigner::generate()
    : new LocalPrivateKeySigner($configuredPrivateKey);
$signature = MessageSigner::sign($message, $signer);
$wireSignature = $signature->toMessageHex();

ExampleEnvironment::output([
    'message' => $message,
    'private_key' => $signer->exportPrivateKey(),
    'public_key' => $signer->publicKeyHex(),
    'address' => $signer->address(),
    'digest' => MessageSigner::digest($message),
    'signature_v2' => $wireSignature,
    'recovered_address' => MessageSigner::recover($message, $wireSignature),
    'verified' => MessageSigner::verify($message, $wireSignature, $signer->address()),
]);
