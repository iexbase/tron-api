<?php

declare(strict_types=1);

/**
 * TronAPI 6.0
 *
 * Copyright (c) 2018-2026 iEXBase.
 *
 * @author  Shamsudin Serderov <steein.shamsudin@gmail.com>
 * @license https://github.com/iexbase/tron-api/blob/master/LICENSE MIT License
 * @link    https://github.com/iexbase/tron-api
 */

namespace IEXBase\TronAPI\Crypto\Wallet;

use IEXBase\TronAPI\Crypto\LocalPrivateKeySigner;
use IEXBase\TronAPI\Exception\CryptoException;
use IEXBase\TronAPI\Value\Address;

/**
 * Derives standard TRON accounts from one protected BIP-39 recovery phrase.
 */
final readonly class HierarchicalWallet
{
    /**
     * Stores the recovery phrase and its in-memory BIP-32 master key.
     */
    private function __construct(
        private MnemonicPhrase $mnemonic,
        private ExtendedKey $masterKey,
    ) {
    }

    /**
     * Generates a new BIP-39 phrase and initializes its BIP-32 master key.
     */
    public static function generate(
        int $wordCount = 12,
        #[\SensitiveParameter] string $passphrase = '',
        string $language = 'english',
    ): self {
        return self::fromMnemonic(MnemonicPhrase::generate($wordCount, $language), $passphrase);
    }

    /**
     * Restores a hierarchical wallet from a validated recovery phrase.
     */
    public static function fromMnemonic(
        MnemonicPhrase $mnemonic,
        #[\SensitiveParameter] string $passphrase = '',
    ): self {
        return new self($mnemonic, ExtendedKey::fromSeed($mnemonic->seed($passphrase)));
    }

    /**
     * Returns the protected phrase object for an explicit secure backup.
     */
    public function mnemonic(): MnemonicPhrase
    {
        return $this->mnemonic;
    }

    /**
     * Derives a complete standard `m/44'/195'/account'/change/index` key.
     */
    public function account(DerivationPath|string $path = "m/44'/195'/0'/0/0"): ExtendedKey
    {
        $derivationPath = is_string($path) ? DerivationPath::fromString($path) : $path;
        if (!$derivationPath->isTronAccount()) {
            throw new CryptoException("A TRON account path must match m/44'/195'/account'/change/index.");
        }

        return $this->masterKey->derivePath($derivationPath);
    }

    /**
     * Returns a local signer for one standard TRON account path.
     */
    public function signer(DerivationPath|string $path = "m/44'/195'/0'/0/0"): LocalPrivateKeySigner
    {
        return $this->account($path)->signer();
    }

    /**
     * Returns the address for one standard TRON account path.
     */
    public function address(DerivationPath|string $path = "m/44'/195'/0'/0/0"): Address
    {
        return $this->account($path)->address();
    }

    /**
     * Exports an account-level xpub for watch-only `change/index` derivation.
     */
    public function accountExtendedPublicKey(int $account = 0): string
    {
        if ($account < 0 || $account > DerivationPath::MAX_INDEX) {
            throw new CryptoException('A TRON account index must be between 0 and 2147483647.');
        }

        return $this->masterKey
            ->derivePath(sprintf("m/44'/195'/%d'", $account))
            ->exportPublic();
    }

    /**
     * Prevents accidental persistence of a phrase or its derived master key.
     *
     * @return array<never, never>
     */
    public function __serialize(): array
    {
        throw new CryptoException('HierarchicalWallet cannot be serialized.');
    }

    /**
     * Redacts the phrase and master key when inspected by a debugger.
     *
     * @return array<string, int|string>
     */
    public function __debugInfo(): array
    {
        return [
            'mnemonic' => '[REDACTED]',
            'masterKey' => '[REDACTED]',
            'language' => $this->mnemonic->language(),
            'wordCount' => $this->mnemonic->wordCount(),
        ];
    }
}
