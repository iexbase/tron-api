<?php

declare(strict_types=1);

use IEXBase\TronAPI\Crypto\Wallet\DerivationPath;
use IEXBase\TronAPI\Crypto\Wallet\ExtendedKey;
use IEXBase\TronAPI\Crypto\Wallet\HierarchicalWallet;
use IEXBase\TronAPI\Crypto\Wallet\MnemonicPhrase;
use IEXBase\TronAPI\Examples\ExampleEnvironment;

require_once __DIR__ . '/ExampleEnvironment.php';

$configuredMnemonic = ExampleEnvironment::optional('TRON_MNEMONIC');
$passphrase = ExampleEnvironment::optional('TRON_PASSPHRASE') ?? '';
$wallet = $configuredMnemonic === null
    ? HierarchicalWallet::generate(passphrase: $passphrase)
    : HierarchicalWallet::fromMnemonic(MnemonicPhrase::parse($configuredMnemonic), $passphrase);
$mnemonic = $wallet->mnemonic();
$firstPath = DerivationPath::tronAccount(account: 0, change: 0, index: 0);
$firstAccount = $wallet->account($firstPath);

$accountXpub = $wallet->accountExtendedPublicKey(0);
$secondWatchOnlyAccount = ExtendedKey::fromBase58($accountXpub)->derivePath('0/1');

ExampleEnvironment::output([
    'mnemonic' => $mnemonic->export(),
    'entropy' => $mnemonic->exportEntropy(),
    'word_count' => $mnemonic->wordCount(),
    'first_path' => (string) $firstPath,
    'first_address' => $firstAccount->address(),
    'first_private_key' => $firstAccount->signer()->exportPrivateKey(),
    'first_public_key' => $firstAccount->publicKeyHex(),
    'first_xprv' => $firstAccount->exportPrivate(),
    'account_xpub' => $accountXpub,
    'second_watch_only_address' => $secondWatchOnlyAccount->address(),
    'watch_only_has_private_key' => $secondWatchOnlyAccount->hasPrivateKey(),
]);
