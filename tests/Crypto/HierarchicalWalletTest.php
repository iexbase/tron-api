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

namespace IEXBase\TronAPI\Tests\Crypto;

use IEXBase\TronAPI\Crypto\Wallet\DerivationPath;
use IEXBase\TronAPI\Crypto\Wallet\ExtendedKey;
use IEXBase\TronAPI\Crypto\Wallet\HierarchicalWallet;
use IEXBase\TronAPI\Crypto\Wallet\MnemonicPhrase;
use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Exception\CryptoException;
use IEXBase\TronAPI\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies BIP-39, BIP-32, xpub, and standard TRON account derivation.
 */
#[CoversClass(DerivationPath::class)]
#[CoversClass(ExtendedKey::class)]
#[CoversClass(HierarchicalWallet::class)]
#[CoversClass(MnemonicPhrase::class)]
final class HierarchicalWalletTest extends TestCase
{
    private const MNEMONIC = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about';
    private const ACCOUNT_XPUB = 'xpub6D1AabNHCupeiLM65ZR9UStMhJ1vCpyV4XbZdyhMZBiJXALQtmn9p42VTQckoHVn8WNqS7dqnJokZHAHcHGoaQgmv8D45oNUKx6DZMNZBCd';

    /**
     * Matches the first official English BIP-39 entropy and seed vector.
     */
    public function testOfficialBip39Vector(): void
    {
        $phrase = MnemonicPhrase::fromEntropy('00000000000000000000000000000000');

        self::assertSame(self::MNEMONIC, $phrase->export());
        self::assertSame('00000000000000000000000000000000', $phrase->exportEntropy());
        self::assertSame(12, $phrase->wordCount());
        self::assertSame(
            'c55257c360c07c72029aebc1b53c05ed0362ada38ead3e3e9efa3708e5349553'
            . '1f09a6987599d18264c1e1c92f2cf141630c7a3c4ab7c81b2f001698e7463b04',
            bin2hex($phrase->seed('TREZOR')),
        );
    }

    /**
     * Matches the complete first official BIP-32 derivation vector.
     */
    public function testOfficialBip32Vector(): void
    {
        $master = ExtendedKey::fromSeed(Hex::toBytes('000102030405060708090a0b0c0d0e0f'));

        self::assertSame(
            'xprv9s21ZrQH143K3QTDL4LXw2F7HEK3wJUD2nW2nRk4stbPy6cq3jPPqjiChkV'
            . 'vvNKmPGJxWUtg6LnF5kejMRNNU3TGtRBeJgk33yuGBxrMPHi',
            $master->exportPrivate(),
        );
        self::assertSame(
            'xpub661MyMwAqRbcFtXgS5sYJABqqG9YLmC4Q1Rdap9gSE8NqtwybGhePY2gZ29E'
            . 'SFjqJoCu1Rupje8YtGqsefD265TMg7usUDFdp6W1EGMcet8',
            $master->exportPublic(),
        );

        $last = $master->derivePath("m/0'/1/2'/2/1000000000");
        self::assertSame(
            'xprvA41z7zogVVwxVSgdKUHDy1SKmdb533PjDz7J6N6mV6uS3ze1ai8FHa8kmHS'
            . 'cGpWmj4WggLyQjgPie1rFSruoUihUZREPSL39UNdE3BBDu76',
            $last->exportPrivate(),
        );
        self::assertSame(
            'xpub6H1LXWLaKsWFhvm6RVpEL9P4KfRZSW7abD2ttkWP3SSQvnyA8FSVqNTEcYF'
            . 'gJS2UaFcxupHiYkro49S8yGasTvXEYBVPamhGW6cFJodrTHy',
            $last->exportPublic(),
        );
    }

    /**
     * Matches the official TronWeb SDK output for accounts zero and one.
     */
    public function testTronWebAccountVectors(): void
    {
        $wallet = HierarchicalWallet::fromMnemonic(MnemonicPhrase::parse(self::MNEMONIC));
        $first = $wallet->account(DerivationPath::tronAccount(index: 0));
        $second = $wallet->account(DerivationPath::tronAccount(index: 1));

        self::assertSame('TUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdH', $first->address()->toBase58());
        self::assertSame(
            'b5a4cea271ff424d7c31dc12a3e43e401df7a40d7412a15750f3f0b6b5449a28',
            $first->signer()->exportPrivateKey(),
        );
        self::assertSame('TSeJkUh4Qv67VNFwY8LaAxERygNdy6NQZK', $second->address()->toBase58());
        self::assertSame(self::ACCOUNT_XPUB, $wallet->accountExtendedPublicKey());
    }

    /**
     * Derives the same public address from an imported account-level xpub.
     */
    public function testWatchOnlyDerivationMatchesPrivateWallet(): void
    {
        $watchOnly = ExtendedKey::fromBase58(self::ACCOUNT_XPUB);
        $address = $watchOnly->derivePath('0/1')->address();

        self::assertFalse($watchOnly->hasPrivateKey());
        self::assertSame(self::ACCOUNT_XPUB, $watchOnly->exportPublic());
        self::assertSame('TSeJkUh4Qv67VNFwY8LaAxERygNdy6NQZK', $address->toBase58());
    }

    /**
     * Rejects hardened derivation from a public-only extended key.
     */
    public function testWatchOnlyKeyCannotDeriveHardenedChild(): void
    {
        $this->expectException(CryptoException::class);

        ExtendedKey::fromBase58(self::ACCOUNT_XPUB)->deriveChild(0, true);
    }

    /**
     * Rejects paths that do not use TRON's registered BIP-44 coin type.
     */
    public function testWalletRejectsNonTronAccountPath(): void
    {
        $wallet = HierarchicalWallet::fromMnemonic(MnemonicPhrase::parse(self::MNEMONIC));
        $this->expectException(CryptoException::class);

        $wallet->account("m/44'/60'/0'/0/0");
    }

    /**
     * Rejects ambiguous and out-of-range derivation path segments.
     */
    public function testPathParserRejectsInvalidChild(): void
    {
        $this->expectException(ValidationException::class);

        DerivationPath::fromString("m/44'/195'/2147483648'/0/0");
    }

    /**
     * Redacts and refuses to serialize recovery and extended private material.
     */
    public function testWalletSecretsAreProtectedFromDebugAndSerialization(): void
    {
        $phrase = MnemonicPhrase::parse(self::MNEMONIC);
        $wallet = HierarchicalWallet::fromMnemonic($phrase);
        $debug = print_r($wallet, true);

        self::assertStringContainsString('[REDACTED]', $debug);
        self::assertStringNotContainsString(self::MNEMONIC, $debug);

        $this->expectException(CryptoException::class);
        serialize($phrase);
    }
}
