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

use IEXBase\TronAPI\Encoding\Hex;
use IEXBase\TronAPI\Exception\CryptoException;
use Nyra\Bip39\Bip39;
use Throwable;

/**
 * Protects a validated BIP-39 recovery phrase and its original entropy.
 *
 * The phrase is sensitive because it controls every key derived from it. It is
 * exposed only through the explicit export method, redacted in debugger output,
 * and cannot be serialized accidentally.
 */
final readonly class MnemonicPhrase
{
    /** @var array<int, int> */
    private const ENTROPY_BITS_BY_WORD_COUNT = [
        12 => 128,
        15 => 160,
        18 => 192,
        21 => 224,
        24 => 256,
    ];

    /**
     * Stores a canonical phrase returned by the selected BIP-39 wordlist.
     */
    private function __construct(
        #[\SensitiveParameter] private string $phrase,
        #[\SensitiveParameter] private string $entropyHex,
        private string $language,
    ) {
    }

    /**
     * Generates a recovery phrase from cryptographically secure random entropy.
     */
    public static function generate(int $wordCount = 12, string $language = 'english'): self
    {
        $entropyBits = self::ENTROPY_BITS_BY_WORD_COUNT[$wordCount] ?? null;
        if ($entropyBits === null) {
            throw new CryptoException('A BIP-39 phrase must contain 12, 15, 18, 21, or 24 words.');
        }

        try {
            return self::parse(Bip39::generateMnemonic($entropyBits, $language), $language);
        } catch (Throwable $exception) {
            throw self::failure('The BIP-39 recovery phrase could not be generated.', $exception);
        }
    }

    /**
     * Restores and verifies a BIP-39 recovery phrase including its checksum.
     */
    public static function parse(#[\SensitiveParameter] string $phrase, string $language = 'english'): self
    {
        try {
            $entropyHex = Bip39::mnemonicToEntropy($phrase, $language);
            $canonicalPhrase = Bip39::entropyToMnemonic($entropyHex, $language);
        } catch (Throwable $exception) {
            throw self::failure('The BIP-39 recovery phrase is invalid.', $exception);
        }

        return new self($canonicalPhrase, Hex::canonicalize($entropyHex), $language);
    }

    /**
     * Creates a recovery phrase from 128, 160, 192, 224, or 256 bits of entropy.
     */
    public static function fromEntropy(#[\SensitiveParameter] string $entropyHex, string $language = 'english'): self
    {
        try {
            return self::parse(Bip39::entropyToMnemonic(Hex::canonicalize($entropyHex), $language), $language);
        } catch (Throwable $exception) {
            throw self::failure('The BIP-39 entropy is invalid.', $exception);
        }
    }

    /**
     * Explicitly exports the recovery phrase for a secure offline backup.
     */
    public function export(): string
    {
        return $this->phrase;
    }

    /**
     * Explicitly exports the source entropy as lowercase hexadecimal text.
     */
    public function exportEntropy(): string
    {
        return $this->entropyHex;
    }

    /**
     * Returns the BIP-39 wordlist language identifier.
     */
    public function language(): string
    {
        return $this->language;
    }

    /**
     * Returns the number of words without exposing the phrase itself.
     */
    public function wordCount(): int
    {
        return intdiv(strlen($this->entropyHex) * 4 * 33, 32 * 11);
    }

    /**
     * Derives the standard 64-byte BIP-39 seed with an optional passphrase.
     */
    public function seed(#[\SensitiveParameter] string $passphrase = ''): string
    {
        try {
            $seed = Bip39::mnemonicToSeed($this->phrase, $passphrase, $this->language);
        } catch (Throwable $exception) {
            throw self::failure('The BIP-39 seed could not be derived.', $exception);
        }

        if (strlen($seed) !== 64) {
            throw new CryptoException('A BIP-39 seed must contain exactly 64 bytes.');
        }

        return $seed;
    }

    /**
     * Prevents accidental persistence of recovery material via serialize().
     *
     * @return array<never, never>
     */
    public function __serialize(): array
    {
        throw new CryptoException('MnemonicPhrase cannot be serialized.');
    }

    /**
     * Redacts all recovery material when inspected by a debugger.
     *
     * @return array<string, int|string>
     */
    public function __debugInfo(): array
    {
        return [
            'phrase' => '[REDACTED]',
            'entropy' => '[REDACTED]',
            'language' => $this->language,
            'wordCount' => $this->wordCount(),
        ];
    }

    /**
     * Preserves this package's exception contract around the BIP-39 dependency.
     */
    private static function failure(string $message, Throwable $exception): CryptoException
    {
        return $exception instanceof CryptoException
            ? $exception
            : new CryptoException($message, 0, $exception);
    }
}
