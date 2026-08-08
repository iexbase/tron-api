<?php

declare(strict_types=1);

use IEXBase\TronAPI\Examples\ExampleEnvironment;
use IEXBase\TronAPI\Value\Amount;

require_once __DIR__ . '/ExampleEnvironment.php';

$tron = ExampleEnvironment::tron();
$firstSigner = ExampleEnvironment::signer('TRON_FIRST_PRIVATE_KEY');
$secondSigner = ExampleEnvironment::signer('TRON_SECOND_PRIVATE_KEY');
$permissionId = (int) ExampleEnvironment::value('TRON_PERMISSION_ID', '2');
$permissions = $tron->permissions()->get($firstSigner->address())
    ?? throw new \RuntimeException('The multisignature owner account does not exist.');
$permission = $permissions->permission($permissionId);
$transaction = $tron->transfers()->createTrxTransfer(
    $firstSigner->address(),
    ExampleEnvironment::address('TRON_RECIPIENT'),
    Amount::fromDecimal(ExampleEnvironment::value('TRON_AMOUNT', '1')),
    permissionId: $permissionId,
);
$transaction = $tron->transactions()->appendSignature(
    $transaction,
    $firstSigner,
    permission: $permission,
);
$transaction = $tron->transactions()->appendSignature(
    $transaction,
    $secondSigner,
    permission: $permission,
);
$weight = $tron->transactions()->signatureWeight($transaction);

ExampleEnvironment::output([
    'transaction_id' => $transaction->id(),
    'signers' => $transaction->signerAddresses(),
    'current_weight' => $weight->currentWeight,
    'threshold' => $weight->permission->threshold,
    'ready' => $weight->hasRequiredWeight(),
]);

if ($weight->hasRequiredWeight()) {
    ExampleEnvironment::broadcastWhenEnabled($transaction);
}
